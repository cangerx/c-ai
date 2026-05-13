<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiChannel extends Model
{
    protected $fillable = [
        'name', 'provider', 'base_url', 'api_key', 'model', 'models',
        'priority', 'request_mode', 'is_active', 'status', 'rate_limit', 'app_name', 'config',
    ];

    protected function casts(): array
    {
        return [
            'models' => 'array',
            'config' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
