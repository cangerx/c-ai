<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RedeemCode extends Model
{
    use HasFactory;
    protected $fillable = [
        'code', 'type', 'credits', 'balance', 'status',
        'created_by', 'used_by', 'used_at', 'expires_at', 'batch_id', 'plan_id', 'agent_plan_id',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'credits' => 'integer',
            'used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
