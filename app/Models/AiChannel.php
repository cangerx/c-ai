<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiChannel extends Model
{
    protected $fillable = [
        'name', 'display_name', 'provider', 'base_url', 'api_key', 'model', 'models',
        'priority', 'request_mode', 'is_active', 'status', 'rate_limit', 'app_name', 'config',
        'current_load', 'error_count', 'max_errors', 'paused_at',
    ];

    protected $hidden = ['api_key'];

    protected function casts(): array
    {
        return [
            'models' => 'array',
            'config' => 'array',
            'is_active' => 'boolean',
            'paused_at' => 'datetime',
        ];
    }
}
