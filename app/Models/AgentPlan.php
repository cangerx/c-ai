<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentPlan extends Model
{
    protected $fillable = [
        'agent_id', 'name', 'price', 'credits', 'balance',
        'is_featured', 'sort_order', 'features', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'agent_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getFeaturesListAttribute(): array
    {
        return $this->features ? array_filter(array_map('trim', explode("\n", $this->features))) : [];
    }
}
