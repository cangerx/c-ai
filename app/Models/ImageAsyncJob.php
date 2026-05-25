<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImageAsyncJob extends Model
{
    protected $fillable = [
        'callback_token',
        'task_id',
        'index',
        'channel_id',
        'upstream_id',
        'status',
        'payload',
        'error',
        'expires_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
            'index' => 'integer',
        ];
    }
}
