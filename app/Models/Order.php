<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'order_no', 'user_id', 'package_id', 'amount', 'credits', 'bonus_balance', 'subject',
        'pay_provider', 'pay_method', 'status',
        'provider_order_no', 'provider_trade_no', 'qr_code',
        'provider_request', 'provider_response',
        'paid_at', 'expires_at', 'credits_granted',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'credits' => 'integer',
        'bonus_balance' => 'decimal:2',
        'provider_request' => 'array',
        'provider_response' => 'array',
        'paid_at' => 'datetime',
        'expires_at' => 'datetime',
        'credits_granted' => 'boolean',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Plan::class, 'package_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
