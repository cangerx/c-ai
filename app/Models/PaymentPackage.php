<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentPackage extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'amount', 'credits', 'bonus_credits', 'is_active', 'sort',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'credits' => 'integer',
        'bonus_credits' => 'integer',
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    public function getTotalCreditsAttribute(): int
    {
        return (int) $this->credits + (int) $this->bonus_credits;
    }
}
