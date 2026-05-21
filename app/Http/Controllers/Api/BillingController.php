<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Plan;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\Payment\PaymentManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BillingController extends Controller
{
    public function __construct(protected PaymentManager $payments)
    {
    }

    public function packages(): JsonResponse
    {
        // 充值入口仅展示一次性套餐；订阅产品走定价页/专用订阅流程
        $items = Plan::where('is_active', true)
            ->where('type', 'once')
            ->orderBy('sort_order')->orderBy('price')
            ->get()
            ->map(fn (Plan $p) => [
                'id' => $p->id,
                'code' => null,
                'name' => $p->name,
                'description' => $p->features,
                'amount' => (float) $p->price,
                'credits' => (int) $p->credits,
                'bonus_credits' => 0,
                'total_credits' => (int) $p->credits,
                'bonus_balance' => (float) ($p->balance ?? 0),
                'is_featured' => (bool) $p->is_featured,
                'type' => $p->type,
            ]);

        return response()->json([
            'ok' => true,
            'items' => $items,
            'channel' => $this->channelStatus(),
        ]);
    }

    /** 当前渠道启用状态与可用支付方式 */
    protected function channelStatus(): array
    {
        $name = config('payment.default', 'tianque');
        $enabled = filter_var(\App\Models\SiteSetting::get('payment_' . $name . '_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $defaults = ['wechat' => true, 'alipay' => true, 'unionpay' => false];
        $methods = [];
        foreach ($defaults as $m => $def) {
            $methods[$m] = filter_var(
                \App\Models\SiteSetting::get('payment_' . $name . '_method_' . $m, $def),
                FILTER_VALIDATE_BOOLEAN
            );
        }
        return [
            'enabled' => $enabled,
            'methods' => $methods,
        ];
    }

    public function createOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'package_id' => 'nullable|integer|exists:plans,id',
            'amount'     => 'nullable|numeric|min:0.01',
            'pay_method' => 'required|in:WECHAT,ALIPAY,UNIONPAY,wechat,alipay,unionpay',
        ]);
        $payMethod = strtoupper($data['pay_method']);

        // 渠道开关
        $providerName = config('payment.default', 'tianque');
        $enabled = filter_var(
            \App\Models\SiteSetting::get('payment_' . $providerName . '_enabled', false),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$enabled) {
            return response()->json(['ok' => false, 'msg' => '支付渠道未启用，请联系管理员'], 503);
        }

        // 单方式开关：未启用的支付方式直接拒绝
        $methodKey = strtolower($payMethod);
        $methodDefault = ['wechat' => true, 'alipay' => true, 'unionpay' => false][$methodKey] ?? false;
        $methodOn = filter_var(
            \App\Models\SiteSetting::get('payment_' . $providerName . '_method_' . $methodKey, $methodDefault),
            FILTER_VALIDATE_BOOLEAN
        );
        if (!$methodOn) {
            $label = ['WECHAT' => '微信', 'ALIPAY' => '支付宝', 'UNIONPAY' => '银联'][$payMethod] ?? $payMethod;
            return response()->json(['ok' => false, 'msg' => $label . '支付暂未开放'], 503);
        }

        $user = $request->user();
        $package = isset($data['package_id']) ? Plan::find($data['package_id']) : null;
        if ($package && $package->type !== 'once') {
            return response()->json(['ok' => false, 'msg' => '此套餐不支持直接充值'], 422);
        }
        if (!$package && !isset($data['amount'])) {
            return response()->json(['ok' => false, 'msg' => '请选择套餐或填写金额'], 422);
        }

        $amount = $package ? (float) $package->price : (float) $data['amount'];
        $credits = $package ? (int) $package->credits : (int) round($amount * 100); // 默认 1元=100积分
        $bonusBalance = $package ? (float) ($package->balance ?? 0) : 0.0;

        $cfg = config('payment.providers.' . config('payment.default'));
        $expiresAt = now()->addMinutes((int) ($cfg['order_expires_minutes'] ?? 10));

        $providerName = config('payment.default', 'tianque');
        $order = DB::transaction(function () use ($user, $package, $amount, $credits, $bonusBalance, $expiresAt, $providerName) {
            return Order::create([
                'order_no'      => $this->generateOrderNo(),
                'user_id'       => $user->id,
                'package_id'    => $package?->id,
                'amount'        => $amount,
                'credits'       => $credits,
                'bonus_balance' => $bonusBalance,
                'subject'       => $package ? $package->name : "充值 ¥{$amount}",
                'pay_provider'  => $providerName,
                'status'        => Order::STATUS_PENDING,
                'expires_at'    => $expiresAt,
            ]);
        });

        try {
            $provider = $this->payments->driver();
            $result = $provider->createOrder($order, $payMethod, $request->ip() ?: '127.0.0.1');

            $order->update([
                'pay_method' => $payMethod,
                'qr_code' => $result['qr_code'] ?? null,
                'provider_order_no' => $result['provider_order_no'] ?? null,
                'provider_response' => $result['raw'] ?? null,
            ]);

            PaymentTransaction::create([
                'order_id' => $order->id,
                'type' => 'create',
                'provider' => $order->pay_provider,
                'result' => empty($result['qr_code']) ? 'fail' : 'success',
                'response' => $result['raw'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::error('Create payment order failed', ['err' => $e->getMessage()]);
            PaymentTransaction::create([
                'order_id' => $order->id,
                'type' => 'create',
                'provider' => $order->pay_provider,
                'result' => 'fail',
                'error_message' => $e->getMessage(),
            ]);
            return response()->json(['ok' => false, 'msg' => '创建订单失败: ' . $e->getMessage()], 500);
        }

        return response()->json([
            'ok' => true,
            'order' => $this->orderPayload($order->fresh()),
        ]);
    }

    public function showOrder(Request $request, string $orderNo): JsonResponse
    {
        $order = Order::where('order_no', $orderNo)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // 主动查询一次（仅 pending 时）
        if ($order->status === Order::STATUS_PENDING && !$order->expires_at?->isPast()) {
            try {
                $provider = $this->payments->driver();
                $result = $provider->queryOrder($order);
                PaymentTransaction::create([
                    'order_id' => $order->id,
                    'type' => 'query',
                    'provider' => $order->pay_provider,
                    'result' => 'success',
                    'provider_trade_no' => $result['provider_trade_no'] ?? null,
                    'response' => $result['raw'] ?? null,
                ]);
                if ($result['status'] === Order::STATUS_PAID) {
                    $this->markPaid($order, $result['provider_trade_no'] ?? null);
                    $order->refresh();
                } elseif (in_array($result['status'], [Order::STATUS_FAILED, Order::STATUS_CANCELLED])) {
                    $order->update(['status' => $result['status']]);
                }
            } catch (\Throwable $e) {
                Log::warning('Query order failed', ['order' => $order->order_no, 'err' => $e->getMessage()]);
            }
        }

        return response()->json(['ok' => true, 'order' => $this->orderPayload($order)]);
    }

    public function notify(Request $request, string $provider): JsonResponse|string
    {
        if ($provider !== 'tianque') {
            return response()->json(['ok' => false], 400);
        }
        $payload = $request->json()->all() ?: $request->all();
        $providerSvc = $this->payments->driver('tianque');

        $data = $providerSvc->verifyNotify($payload);
        if (!$data) {
            return response()->json(['ok' => false, 'msg' => 'verify failed'], 400);
        }

        $ordNo = $data['ordNo'] ?? null;
        $order = $ordNo ? Order::where('order_no', $ordNo)->first() : null;
        if (!$order) {
            return response()->json(['ok' => false, 'msg' => 'order not found'], 404);
        }

        PaymentTransaction::create([
            'order_id' => $order->id,
            'type' => 'notify',
            'provider' => $order->pay_provider,
            'result' => 'success',
            'provider_trade_no' => $data['transactionId'] ?? null,
            'request' => $payload,
        ]);

        $status = strtoupper((string) ($data['tradeStatus'] ?? $data['status'] ?? ''));
        if ($status === 'SUCCESS' || $status === 'PAID') {
            $this->markPaid($order, $data['transactionId'] ?? null);
        }

        return $providerSvc->notifySuccessResponse();
    }

    public function userOrders(Request $request): JsonResponse
    {
        $perPage = min((int) $request->input('per_page', 15), 50);
        $orders = Order::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        $orders->getCollection()->transform(fn ($o) => $this->orderPayload($o));
        return response()->json($orders);
    }

    /* =================== helpers =================== */

    protected function generateOrderNo(): string
    {
        return 'CA' . date('YmdHis') . strtoupper(Str::random(6));
    }

    protected function markPaid(Order $order, ?string $tradeNo): void
    {
        DB::transaction(function () use ($order, $tradeNo) {
            $order = Order::lockForUpdate()->find($order->id);
            if (!$order || $order->credits_granted) return;
            if ($order->status === Order::STATUS_PAID) return;

            $order->update([
                'status' => Order::STATUS_PAID,
                'paid_at' => now(),
                'provider_trade_no' => $tradeNo ?: $order->provider_trade_no,
            ]);

            // 发放积分 + 赠送余额（幂等：credits_granted 标记）
            $user = User::lockForUpdate()->find($order->user_id);
            if ($user) {
                $user->credits = (int) $user->credits + (int) $order->credits;
                if ((float) $order->bonus_balance > 0) {
                    $user->balance = (float) $user->balance + (float) $order->bonus_balance;
                }
                $user->total_recharged = (float) $user->total_recharged + (float) $order->amount;
                $user->save();
            }
            $order->update(['credits_granted' => true]);
        });
    }

    protected function orderPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_no' => $order->order_no,
            'amount' => (float) $order->amount,
            'credits' => $order->credits,
            'subject' => $order->subject,
            'pay_provider' => $order->pay_provider,
            'pay_method' => $order->pay_method,
            'status' => $order->status,
            'qr_code' => $order->qr_code,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'expires_at' => $order->expires_at?->toIso8601String(),
            'created_at' => $order->created_at?->toIso8601String(),
        ];
    }
}
