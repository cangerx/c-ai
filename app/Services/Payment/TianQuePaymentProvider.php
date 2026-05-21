<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Services\Payment\Contracts\PaymentProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TianQuePaymentProvider implements PaymentProvider
{
    /** tranSts 值映射（来自官方文档） */
    public const STATUS_MAP = [
        'SUCCESS'  => Order::STATUS_PAID,
        'PAYING'   => Order::STATUS_PENDING,
        'FAIL'     => Order::STATUS_FAILED,
        'CLOSED'   => Order::STATUS_CANCELLED,
        'CANCELED' => Order::STATUS_CANCELLED,
        'REFUNDSUC'  => Order::STATUS_REFUNDED,
        'REFUNDING'  => Order::STATUS_PAID,
        'REFUNDFAIL' => Order::STATUS_PAID,
    ];

    public function __construct(protected array $cfg)
    {
    }

    public function createOrder(Order $order, string $payMethod, string $clientIp = '127.0.0.1'): array
    {
        // ⚠️ reqData 内部顺序就是签名时的顺序，禁止排序
        $reqData = $this->compactRequired([
            'mno'         => $this->cfg['mno'],
            'subMechId'   => $this->cfg['sub_mech_id'] ?? null,
            'ordNo'       => $order->order_no,
            'amt'         => number_format((float) $order->amount, 2, '.', ''),
            'payType'     => strtoupper($payMethod),
            'subject'     => $order->subject ?: '充值',
            'tradeSource' => '01',
            'trmIp'       => $clientIp,
            'notifyUrl'   => $this->cfg['notify_url'],
        ]);

        $response = $this->call('/order/activeScan', $reqData);
        $this->assertSuccess($response);

        $data = $this->extractRespData($response);
        $payUrl = $data['payUrl'] ?? $data['qrCode'] ?? '';
        if ($payUrl === '') {
            throw new \RuntimeException('天阙下单失败: 响应缺少 payUrl');
        }

        return [
            'qr_code' => $payUrl,
            'provider_order_no' => $data['uuid'] ?? $data['sxfUuid'] ?? null,
            'raw' => $response,
        ];
    }

    public function queryOrder(Order $order): array
    {
        $reqData = $this->compactRequired([
            'mno'   => $this->cfg['mno'],
            'ordNo' => $order->order_no,
            'uuid'  => $order->provider_order_no ?? null,
        ]);

        $response = $this->call('/query/tradeQuery', $reqData);
        $this->assertSuccess($response);
        $data = $this->extractRespData($response);

        $raw = strtoupper((string) ($data['tranSts'] ?? ''));
        $status = self::STATUS_MAP[$raw] ?? Order::STATUS_PENDING;

        return [
            'status' => $status,
            'provider_trade_no' => $data['transactionId'] ?? $data['sxfUuid'] ?? null,
            'raw' => $response,
        ];
    }

    public function verifyNotify(array $payload): ?array
    {
        Log::info('TianQue notify received', ['payload' => $payload]);

        $code = (string) ($payload['code'] ?? '');
        if ($code !== '0000') {
            Log::warning('TianQue notify outer code not 0000', ['code' => $code]);
            return null;
        }

        $respData = $payload['respData'] ?? $payload['reqData'] ?? $payload['data'] ?? $payload;
        if (!is_array($respData)) {
            Log::warning('TianQue notify missing respData');
            return null;
        }

        return $respData;
    }

    public function refundOrder(Order $order, string $refundOrderNo, ?float $refundAmount = null): array
    {
        $amt = $refundAmount ?? (float) $order->amount;
        $reqData = $this->compactRequired([
            'mno'          => $this->cfg['mno'],
            'ordNo'        => $refundOrderNo,
            'origOrderNo'  => $order->order_no,
            'amt'          => number_format($amt, 2, '.', ''),
        ]);

        $response = $this->call('/order/refund', $reqData);
        // 退款接口 bizCode=2002 表示"已退款/重复退款"，视为成功
        $this->assertSuccess($response, ['2002']);
        $data = $this->extractRespData($response);

        return [
            'refund_order_no' => $refundOrderNo,
            'provider_trade_no' => $data['transactionId'] ?? $data['sxfUuid'] ?? null,
            'raw' => $response,
        ];
    }

    public function notifySuccessResponse(): string
    {
        return 'SUCCESS';
    }

    /* ============ internal ============ */

    protected function call(string $path, array $reqData): array
    {
        // 外层字段（注意：reqId 32位hex；timestamp 14位数字；version 商户=1.2）
        $bean = [
            'signType'  => $this->cfg['sign_type'] ?? 'RSA',
            'version'   => $this->cfg['version'] ?? '1.0',
            'orgId'     => $this->cfg['org_id'],
            'reqId'     => $this->generateReqId(),
            'timestamp' => date('YmdHis'),
            'reqData'   => $reqData,
        ];
        $bean['sign'] = $this->sign($this->getSignContent($bean));

        $host = $this->resolveHost();
        $resp = Http::timeout(15)
            ->withHeaders(['Content-Type' => 'application/json;charset=UTF-8'])
            ->post($host . $path, $bean);

        $json = $resp->json() ?: [];
        Log::debug('TianQue call', ['path' => $path, 'request' => $bean, 'response' => $json]);
        return $json;
    }

    /** UUID v4 hex, 32 chars, no dashes */
    protected function generateReqId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** 过滤 null / 空字符串（保留 0/'0'） */
    protected function compactRequired(array $arr): array
    {
        return array_filter($arr, fn ($v) => $v !== null && $v !== '');
    }

    /** 提取响应内的 respData（兼容外层未包裹的实现） */
    protected function extractRespData(array $resp): array
    {
        return $resp['respData'] ?? $resp['data'] ?? $resp;
    }

    /**
     * 顺行付/天阙响应成功语义：
     *  - 外层 code = 0000 才表示请求/路由成功
     *  - 内层 respData.bizCode (若存在) = 0000 才表示业务成功
     * 任何一层非成功都抛出，便于把真实错误透出。
     */
    protected function assertSuccess(array $resp, array $extraBizCodes = []): void
    {
        $outerCode = (string) ($resp['code'] ?? '');
        $outerMsg  = (string) ($resp['msg']  ?? '');
        if ($outerCode !== '' && $outerCode !== '0000') {
            throw new \RuntimeException("天阙网关错误: [$outerCode] $outerMsg");
        }
        $data = $resp['respData'] ?? $resp['data'] ?? null;
        if (is_array($data) && isset($data['bizCode'])) {
            $bizCode = (string) $data['bizCode'];
            $bizMsg  = (string) ($data['bizMsg'] ?? '');
            $ok = $bizCode === '0000' || in_array($bizCode, $extraBizCodes, true);
            if (!$ok) {
                throw new \RuntimeException("天阙业务错误: [$bizCode] $bizMsg");
            }
        }
    }

    protected function resolveHost(): string
    {
        $sandbox = (bool) ($this->cfg['sandbox'] ?? true);
        return $sandbox ? $this->cfg['host'] : ($this->cfg['host_production'] ?? $this->cfg['host']);
    }

    public function getSignContent(array $params): string
    {
        ksort($params);
        $parts = [];
        foreach ($params as $k => $v) {
            if ($k === 'sign') continue;
            if ($v === null || $v === '') continue;
            if (is_array($v)) {
                $parts[] = $k . '=' . json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $parts[] = $k . '=' . $v;
            }
        }
        return implode('&', $parts);
    }

    protected function sign(string $data): string
    {
        $pem = $this->formatKey($this->cfg['private_key'] ?? '', 'priv');
        $pk = openssl_pkey_get_private($pem);
        if (!$pk) {
            throw new \RuntimeException('天阙商户私钥加载失败（请确认是 PKCS8 格式，PEM 头为 BEGIN PRIVATE KEY）');
        }
        $algo = ($this->cfg['sign_type'] ?? 'RSA') === 'RSA2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;
        openssl_sign($data, $signature, $pk, $algo);
        return base64_encode($signature);
    }

    /**
     * 把裸 base64 key 包装成 PEM。
     * 私钥统一使用 PKCS8（BEGIN PRIVATE KEY），符合天阙官方规范。
     * 已带 PEM 头的直接返回。
     */
    protected function formatKey(string $key, string $type): string
    {
        $key = trim($key);
        if (str_contains($key, 'BEGIN')) return $key;
        $wrapped = wordwrap($key, 64, "\n", true);
        if ($type === 'priv') {
            return "-----BEGIN PRIVATE KEY-----\n{$wrapped}\n-----END PRIVATE KEY-----";
        }
        return "-----BEGIN PUBLIC KEY-----\n{$wrapped}\n-----END PUBLIC KEY-----";
    }
}
