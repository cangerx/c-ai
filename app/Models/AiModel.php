<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class AiModel extends Model
{
    protected $fillable = ['model_id', 'display_name', 'type', 'config', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'config' => 'array'];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('api:config'));
        static::deleted(fn () => Cache::forget('api:config'));
    }
}
