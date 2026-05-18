<?php

namespace App\Http\Controllers\Api;

use App\Models\WithdrawalRequest;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalController
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'amount' => 'required|integer|min:1',
            'payment_method' => 'required|in:alipay,wechat,bank',
            'payment_account' => 'required|string|max:200',
        ]);

        $user = $request->user();

        if (!$user->is_distributor) {
            return response()->json(['message' => '仅分销员可申请提现'], 403);
        }

        $amount = (int) $request->input('amount');

        if ($user->commission_credits < $amount) {
            return response()->json(['message' => '佣金积分不足'], 422);
        }

        $agentId = app(BillingService::class)->findAgentId($user);
        if (!$agentId) {
            return response()->json(['message' => '未找到归属代理'], 422);
        }

        $withdrawal = DB::transaction(function () use ($user, $amount, $agentId, $request) {
            $user = \App\Models\User::lockForUpdate()->find($user->id);
            if ($user->commission_credits < $amount) {
                return null;
            }
            $user->decrement('commission_credits', $amount);

            return WithdrawalRequest::create([
                'user_id' => $user->id,
                'agent_id' => $agentId,
                'amount' => $amount,
                'status' => 'pending',
                'payment_method' => $request->input('payment_method'),
                'payment_account' => $request->input('payment_account'),
            ]);
        });

        if (!$withdrawal) {
            return response()->json(['message' => '佣金积分不足'], 422);
        }

        return response()->json(['message' => '提现申请已提交', 'id' => $withdrawal->id]);
    }

    public function index(Request $request): JsonResponse
    {
        $records = WithdrawalRequest::where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'amount', 'status', 'payment_method', 'agent_note', 'created_at', 'agent_processed_at']);

        return response()->json($records);
    }
}
