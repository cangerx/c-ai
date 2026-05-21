<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'type', 'provider', 'result', 'provider_trade_no',
        'request', 'response', 'error_message', 'created_at',
    ];

    protected $casts = [
        'request' => 'array',
        'response' => 'array',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
