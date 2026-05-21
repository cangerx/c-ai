<?php

namespace App\Services\Payment;

use App\Models\Order;
use App\Services\Payment\Contracts\PaymentProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TianQuePaymentProvider implements PaymentProvider
{
    /** 天阙平台公钥（固定公开值，用于回调验签） */
    public const TIANQUE_PUBLIC_KEY_SANDBOX = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCOmsrFtFPTnEzfpJ/hDl5RODBxw4i9Ex3NmmG/N7A1+by032zZZgLLpdNh8y5otjFY0E37Nyr4FGKFRSSuDiTk8vfx3pv6ImS1Rxjjg4qdVHIfqhCeB0Z2ZPuBD3Gbj8hHFEtXZq8+msAFu/5ZQjiVhgs5WWBjh54LYWSum+d9+wIDAQAB';
    public const TIANQUE_PUBLIC_KEY_PROD = 'MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAjo1+KBcvwDSIo+nMYLeOJ19Ju4ii0xH66ZxFd869EWFWk/EJa3xIA2+4qGf/Ic7m7zi/NHuCnfUtUDmUdP0JfaZiYwn+1Ek7tYAOc1+1GxhzcexSJLyJlR2JLMfEM+rZooW4Ei7q3a8jdTWUNoak/bVPXnLEVLrbIguXABERQ0Ze0X9Fs0y/zkQFg8UjxUN88g2CRfMC6LldHm7UBo+d+WlpOYH7u0OTzoLLiP/04N1cfTgjjtqTBI7qkOGxYs6aBZHG1DJ6WdP+5w+ho91sBTVajsCxAaMoExWQM2ipf/1qGdsWmkZScPflBqg7m0olOD87ymAVP/3Tcbvi34bDfwIDAQAB';

    /** tranSts 值映射（来自官方 skill 文档） */
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
            'tradeSource' => '02',
            'trmIp'       => $clientIp,
            'notifyUrl'   => $this->cfg['notify_url'],
        ]);

        $response = $this->call('/order/activePlusScan', $reqData);
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
        $sign = $payload['sign'] ?? null;
        if (!$sign) return null;

        $copy = $payload;
        unset($copy['sign']);
        $signContent = $this->getSignContent($copy);

        $ok = $this->verifySignature($signContent, $sign);
        if (!$ok) {
            Log::warning('TianQue notify sign mismatch', ['payload' => $payload]);
            return null;
        }

        // 回调结构与下单返回相同：respData 装着业务字段
        return $payload['respData'] ?? $payload['reqData'] ?? $payload['data'] ?? $payload;
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
            'version'   => $this->cfg['version'] ?? '1.2',
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
    protected function assertSuccess(array $resp): void
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
            if ($bizCode !== '0000') {
                throw new \RuntimeException("天阙业务错误: [$bizCode] $bizMsg");
            }
        }
    }

    protected function resolveHost(): string
    {
        $sandbox = (bool) ($this->cfg['sandbox'] ?? true);
        return $sandbox ? $this->cfg['host'] : ($this->cfg['host_production'] ?? $this->cfg['host']);
    }

    /**
     * 平台公钥优先级：
     *  1. cfg['public_key']（用户在后台显式覆盖时）
     *  2. 内置常量（按 sandbox/生产环境选择）
     */
    protected function resolvePublicKey(): string
    {
        $custom = trim((string) ($this->cfg['public_key'] ?? ''));
        if ($custom !== '') return $custom;
        $sandbox = (bool) ($this->cfg['sandbox'] ?? true);
        return $sandbox ? self::TIANQUE_PUBLIC_KEY_SANDBOX : self::TIANQUE_PUBLIC_KEY_PROD;
    }

    public function getSignContent(array $params): string
    {
        ksort($params);
        $parts = [];
        foreach ($params as $k => $v) {
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

    protected function verifySignature(string $data, string $sign): bool
    {
        $publicKey = $this->resolvePublicKey();
        $pem = $this->formatKey($publicKey, 'pub');
        $pk = openssl_pkey_get_public($pem);
        if (!$pk) return false;
        $algo = ($this->cfg['sign_type'] ?? 'RSA') === 'RSA2' ? OPENSSL_ALGO_SHA256 : OPENSSL_ALGO_SHA1;
        return openssl_verify($data, base64_decode($sign), $pk, $algo) === 1;
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
