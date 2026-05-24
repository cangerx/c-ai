<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    protected $fillable = ['key', 'value', 'group'];

    protected static ?array $allSettings = null;

    public static function getAll(): array
    {
        if (self::$allSettings !== null && !app()->runningInConsole()) {
            return self::$allSettings;
        }

        $settings = Cache::remember('site_settings:all', 300, function () {
            try {
                return static::all()->pluck('value', 'key')->toArray();
            } catch (\Throwable $e) {
                return [];
            }
        });

        if (!app()->runningInConsole()) {
            self::$allSettings = $settings;
        }

        return $settings;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = static::getAll();
        return array_key_exists($key, $settings) ? $settings[$key] : $default;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        self::$allSettings = null;
        Cache::forget('site_settings:all');
        Cache::forget("site_setting:{$key}");
        Cache::forget('api:config');
    }
}
