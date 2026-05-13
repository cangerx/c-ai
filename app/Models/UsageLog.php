<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UsageLog extends Model
{
    protected $fillable = [
        'user_id', 'app_name', 'task_id', 'channel_id',
        'model', 'quality', 'cost_credits', 'cost_balance',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'cost_balance' => 'decimal:2',
            'cost_credits' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(AiChannel::class, 'channel_id');
    }
}
