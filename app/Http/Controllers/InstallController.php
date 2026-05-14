<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class InstallController extends Controller
{
    public function index()
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }
        return view('install', ['checks' => $this->checkEnv()]);
    }

    public function run(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $request->validate([
            'site_name' => 'required|string|max:100',
            'site_url' => 'required|url',
            'admin_email' => 'required|email',
            'admin_password' => 'required|min:6',
        ]);

        // 写 .env
        $envPath = base_path('.env');
        if (!file_exists($envPath)) {
            copy(base_path('.env.example'), $envPath);
        }

        $this->setEnv('APP_NAME', $request->input('site_name'));
        $this->setEnv('APP_URL', $request->input('site_url'));
        $this->setEnv('APP_ENV', 'production');
        $this->setEnv('APP_DEBUG', 'false');

        // 确保 SQLite 文件存在
        $dbPath = database_path('database.sqlite');
        if (!file_exists($dbPath)) {
            touch($dbPath);
        }

        // 生成 key
        Artisan::call('key:generate', ['--force' => true]);

        // 迁移
        Artisan::call('migrate', ['--force' => true]);

        // 创建存储链接
        try {
            Artisan::call('storage:link');
        } catch (\Throwable) {}

        // 创建管理员
        $user = User::create([
            'name' => 'Admin',
            'email' => $request->input('admin_email'),
            'password' => Hash::make($request->input('admin_password')),
            'is_admin' => true,
            'credits' => 999,
            'balance' => 0,
        ]);

        // 保存站点名
        SiteSetting::set('site_name', $request->input('site_name'), 'general');

        // 标记已安装
        file_put_contents(storage_path('installed'), date('Y-m-d H:i:s'));

        return view('install-done', [
            'site_url' => $request->input('site_url'),
            'admin_email' => $request->input('admin_email'),
        ]);
    }

    private function isInstalled(): bool
    {
        return file_exists(storage_path('installed'));
    }

    private function checkEnv(): array
    {
        return [
            ['name' => 'PHP >= 8.3', 'ok' => version_compare(PHP_VERSION, '8.3.0', '>=')],
            ['name' => 'PDO SQLite', 'ok' => extension_loaded('pdo_sqlite')],
            ['name' => 'GD 扩展', 'ok' => extension_loaded('gd')],
            ['name' => 'Fileinfo 扩展', 'ok' => extension_loaded('fileinfo')],
            ['name' => 'Ctype 扩展', 'ok' => extension_loaded('ctype')],
            ['name' => 'JSON 扩展', 'ok' => extension_loaded('json')],
            ['name' => 'Mbstring 扩展', 'ok' => extension_loaded('mbstring')],
            ['name' => 'OpenSSL 扩展', 'ok' => extension_loaded('openssl')],
            ['name' => 'storage/ 可写', 'ok' => is_writable(storage_path())],
            ['name' => 'database/ 可写', 'ok' => is_writable(database_path())],
            ['name' => '.env 可写', 'ok' => is_writable(base_path()) || (file_exists(base_path('.env')) && is_writable(base_path('.env')))],
        ];
    }

    private function setEnv(string $key, string $value): void
    {
        $envPath = base_path('.env');
        $content = file_get_contents($envPath);
        $escaped = str_contains($value, ' ') ? "\"$value\"" : $value;

        if (preg_match("/^{$key}=.*/m", $content)) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$escaped}", $content);
        } else {
            $content .= "\n{$key}={$escaped}";
        }

        file_put_contents($envPath, $content);
    }
}
