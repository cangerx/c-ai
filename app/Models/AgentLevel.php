<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentLevel extends Model
{
    protected $fillable = ['name', 'min_recharge', 'price_per_credit', 'sort_order'];

    protected function casts(): array
    {
        return [
            'min_recharge' => 'decimal:2',
            'price_per_credit' => 'decimal:4',
        ];
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('min_recharge');
    }
}
