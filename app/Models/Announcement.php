<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Announcement extends Model
{
    protected $fillable = ['content', 'url', 'enabled', 'sort'];

    protected $casts = ['enabled' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('api:config'));
        static::deleted(fn () => Cache::forget('api:config'));
    }
}
