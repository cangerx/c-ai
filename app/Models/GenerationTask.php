<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class GenerationTask extends Model
{
    use HasFactory;
    protected $primaryKey = 'task_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'task_id', 'user_id', 'status', 'mode', 'model', 'prompt',
        'size', 'quality', 'count', 'is_public', 'input_count',
        'message', 'error', 'items', 'files', 'completed_at', 'attempts',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'files' => 'array',
            'is_public' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function usageLog(): HasOne
    {
        return $this->hasOne(UsageLog::class, 'task_id', 'task_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
