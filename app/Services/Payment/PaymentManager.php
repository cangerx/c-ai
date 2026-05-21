<?php

namespace App\Services\Payment;

use App\Services\Payment\Contracts\PaymentProvider;

class PaymentManager
{
    public function driver(?string $name = null): PaymentProvider
    {
        $name = $name ?: config('payment.default', 'tianque');
        $cfg = config('payment.providers.' . $name) ?? [];
        $cfg = $this->mergeDbOverrides($name, $cfg);
        return match ($cfg['driver'] ?? $name) {
            'tianque' => new TianQuePaymentProvider($cfg),
            default => throw new \InvalidArgumentException("Unsupported payment driver: {$cfg['driver']}"),
        };
    }

    /**
     * 数据库 SiteSetting 覆盖 env 默认（key 形如 payment_tianque_org_id）
     */
    protected function mergeDbOverrides(string $name, array $cfg): array
    {
        if (!class_exists(\App\Models\SiteSetting::class)) return $cfg;
        $prefix = 'payment_' . $name . '_';
        $map = [
            'enabled', 'sandbox', 'host', 'host_production', 'org_id', 'mno', 'sub_mech_id',
            'private_key', 'public_key', 'sign_type', 'version', 'notify_url',
        ];
        foreach ($map as $key) {
            $val = \App\Models\SiteSetting::get($prefix . $key);
            if ($val === null || $val === '') continue;
            if ($key === 'sandbox') $val = filter_var($val, FILTER_VALIDATE_BOOLEAN);
            $cfg[$key] = $val;
        }
        return $cfg;
    }
}
