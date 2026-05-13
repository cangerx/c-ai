<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}
