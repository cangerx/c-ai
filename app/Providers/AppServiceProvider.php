<?php

namespace App\Providers;

use App\Services\AppLoader;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AppLoader::class);

        $loader = $this->app->make(AppLoader::class);
        foreach ($loader->getProviders() as $provider) {
            $this->app->register($provider);
        }
    }

    public function boot(): void
    {
        $loader = $this->app->make(AppLoader::class);
        View::share('appMenuGroups', $loader->getMenuItems());
        $this->configureLivewireUploads();
        $this->configureUrlScheme();

        // 动态加载邮件配置
        try {
            $host = \App\Models\SiteSetting::get('mail_host');
            if ($host) {
                Config::set('mail.default', 'smtp');
                Config::set('mail.mailers.smtp.host', $host);
                Config::set('mail.mailers.smtp.port', \App\Models\SiteSetting::get('mail_port', 465));
                Config::set('mail.mailers.smtp.username', \App\Models\SiteSetting::get('mail_username'));
                Config::set('mail.mailers.smtp.password', \App\Models\SiteSetting::get('mail_password'));
                Config::set('mail.mailers.smtp.encryption', \App\Models\SiteSetting::get('mail_encryption', 'ssl'));
                Config::set('mail.from.address', \App\Models\SiteSetting::get('mail_from_address', \App\Models\SiteSetting::get('mail_username')));
                Config::set('mail.from.name', \App\Models\SiteSetting::get('mail_from_name', 'CANG-AI'));
            }
        } catch (\Throwable $e) {
            // 数据库未就绪时忽略
        }

        RateLimiter::for('login', fn (Request $request) =>
            Limit::perMinute(5)->by($request->input('email', '') . '|' . $request->ip())
                ->response(fn () => response()->json(['message' => '操作过于频繁，请稍后再试'], 429))
        );

        RateLimiter::for('register', fn (Request $request) =>
            Limit::perMinute(3)->by($request->ip())
                ->response(fn () => response()->json(['message' => '操作过于频繁，请稍后再试'], 429))
        );
    }

    protected function configureLivewireUploads(): void
    {
        $maxKilobytes = max(1, (int) config('system.backup_upload_max_mb', 512)) * 1024;

        Config::set('livewire.temporary_file_upload.rules', [
            'required',
            'file',
            'max:' . $maxKilobytes,
        ]);
        Config::set('livewire.temporary_file_upload.max_upload_time', (int) env('SYSTEM_BACKUP_UPLOAD_MAX_TIME', 30));
    }

    protected function configureUrlScheme(): void
    {
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
