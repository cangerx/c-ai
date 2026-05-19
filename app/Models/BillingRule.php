<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class BillingRule extends Model
{
    protected $fillable = [
        'app_name', 'model_pattern', 'quality',
        'cost_credits', 'cost_balance',
    ];

    protected function casts(): array
    {
        return [
            'cost_balance' => 'decimal:2',
            'cost_credits' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('api:config'));
        static::deleted(fn () => Cache::forget('api:config'));
    }
}
