<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function agents(): HasMany
    {
        return $this->hasMany(User::class, 'agent_level_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('min_recharge');
    }
}
