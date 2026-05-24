<?php

namespace App\Http\Controllers;

use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InstallController extends Controller
{
    public function index()
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }
        $checks = $this->checkEnv();
        $allPass = collect($checks)->every(fn ($check) => $check['ok']);

        return view('install', [
            'step' => $this->isPreconfigured() && $allPass ? 3 : 1,
            'checks' => $checks,
            'preconfigured' => $this->isPreconfigured(),
        ]);
    }

    public function step2()
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }
        return view('install', [
            'step' => $this->isPreconfigured() ? 3 : 2,
            'preconfigured' => $this->isPreconfigured(),
        ]);
    }

    public function testDb(Request $request)
    {
        if ($this->isInstalled()) {
            return response()->json(['ok' => false, 'msg' => '已安装']);
        }

        $driver = $request->input('db_connection', 'mysql');

        if ($driver === 'sqlite') {
            $dbPath = database_path('database.sqlite');
            if (!file_exists($dbPath)) {
                @touch($dbPath);
            }
            return response()->json(['ok' => file_exists($dbPath), 'msg' => file_exists($dbPath) ? '连接成功' : '无法创建 SQLite 文件']);
        }

        try {
            $pdo = new \PDO(
                "mysql:host={$request->input('db_host')};port={$request->input('db_port')};dbname={$request->input('db_database')}",
                $request->input('db_username'),
                $request->input('db_password'),
                [\PDO::ATTR_TIMEOUT => 5]
            );
            $pdo->query('SELECT 1');
            return response()->json(['ok' => true, 'msg' => '连接成功']);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'msg' => $e->getMessage()]);
        }
    }

    public function run(Request $request)
    {
        if ($this->isInstalled()) {
            return redirect('/');
        }

        $rules = [
            'site_name' => 'required|string|max:100',
            'site_url' => 'required|url',
            'admin_email' => 'required|email',
            'admin_password' => 'required|min:6',
        ];

        if (!$this->isPreconfigured()) {
            $rules['db_connection'] = 'required|in:mysql,sqlite';
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return view('install', [
                'step' => 3,
                'error' => $validator->errors()->first(),
            ]);
        }

        try {
            $envPath = base_path('.env');
            if (!file_exists($envPath)) {
                copy(base_path($this->isPreconfigured() ? '.env.docker.example' : '.env.example'), $envPath);
            }

            $dbConn = $this->isPreconfigured()
                ? (env('DB_CONNECTION') ?: 'mysql')
                : $request->input('db_connection');

            if (!$this->isPreconfigured()) {
                // 数据库配置
                $this->setEnv('DB_CONNECTION', $dbConn);
            }

            if (!$this->isPreconfigured() && $dbConn === 'mysql') {
                $this->setEnv('DB_HOST', $request->input('db_host', '127.0.0.1'));
                $this->setEnv('DB_PORT', $request->input('db_port', '3306'));
                $this->setEnv('DB_DATABASE', $request->input('db_database'));
                $this->setEnv('DB_USERNAME', $request->input('db_username'));
                $this->setEnv('DB_PASSWORD', $request->input('db_password', ''));
            } elseif ($dbConn === 'sqlite') {
                $dbPath = database_path('database.sqlite');
                if (!file_exists($dbPath)) {
                    touch($dbPath);
                }
            }

            // 站点配置
            $this->setEnv('APP_NAME', $request->input('site_name'));
            $this->setEnv('APP_URL', $request->input('site_url'));
            $this->setEnv('APP_ENV', 'production');
            $this->setEnv('APP_DEBUG', 'false');

            $this->ensureAppKey();

            // 重新加载数据库配置
            config([
                'database.default' => $dbConn,
                'database.connections.mysql.host' => $this->isPreconfigured() ? env('DB_HOST', 'mysql') : $request->input('db_host', '127.0.0.1'),
                'database.connections.mysql.port' => $this->isPreconfigured() ? env('DB_PORT', '3306') : $request->input('db_port', '3306'),
                'database.connections.mysql.database' => $this->isPreconfigured() ? env('DB_DATABASE', 'cang_ai') : $request->input('db_database'),
                'database.connections.mysql.username' => $this->isPreconfigured() ? env('DB_USERNAME', 'cang_ai') : $request->input('db_username'),
                'database.connections.mysql.password' => $this->isPreconfigured() ? env('DB_PASSWORD', '') : $request->input('db_password', ''),
            ]);
            DB::purge();

            // 迁移
            Artisan::call('migrate', ['--force' => true]);

            if (!$this->isPreconfigured()) {
                // 迁移完成后切换到 database 驱动
                $this->setEnv('SESSION_DRIVER', 'database');
                $this->setEnv('CACHE_STORE', 'database');
                $this->setEnv('QUEUE_CONNECTION', 'database');
            }

            // 创建存储链接
            try {
                Artisan::call('storage:link');
            } catch (\Throwable) {}

            // 创建管理员
            $admin = new User([
                'name' => 'Admin',
                'email' => $request->input('admin_email'),
                'password' => Hash::make($request->input('admin_password')),
            ]);
            $admin->role = 'admin';
            $admin->status = 'active';
            $admin->credits = 999;
            $admin->balance = 0;
            $admin->save();

            // 保存站点名
            SiteSetting::set('site_name', $request->input('site_name'), 'general');
            SiteSetting::set('site_description', SiteSetting::get('site_description', 'AI 图像生成平台'), 'general');
            SiteSetting::set('billing_low_credits', SiteSetting::get('billing_low_credits', '1'), 'billing');
            SiteSetting::set('billing_medium_credits', SiteSetting::get('billing_medium_credits', '2'), 'billing');
            SiteSetting::set('billing_high_credits', SiteSetting::get('billing_high_credits', '4'), 'billing');
            SiteSetting::set('register_gift_credits', SiteSetting::get('register_gift_credits', '5'), 'billing');

            // 标记已安装
            file_put_contents(storage_path('installed'), date('Y-m-d H:i:s'));

            Artisan::call('app:optimize');

            return view('install-done', [
                'site_url' => $request->input('site_url'),
                'admin_email' => $request->input('admin_email'),
            ]);
        } catch (\Throwable $e) {
            return view('install', [
                'step' => 3,
                'error' => '安装失败：' . $e->getMessage(),
            ]);
        }
    }

    private function isInstalled(): bool
    {
        return file_exists(storage_path('installed'));
    }

    private function isPreconfigured(): bool
    {
        return filter_var(env('INSTALL_PRECONFIGURED', false), FILTER_VALIDATE_BOOL);
    }

    private function checkEnv(): array
    {
        $checks = [
            ['name' => 'PHP >= 8.3', 'ok' => version_compare(PHP_VERSION, '8.3.0', '>=')],
            ['name' => 'PDO MySQL', 'ok' => extension_loaded('pdo_mysql')],
            ['name' => 'Fileinfo', 'ok' => extension_loaded('fileinfo')],
            ['name' => 'Ctype', 'ok' => extension_loaded('ctype')],
            ['name' => 'Mbstring', 'ok' => extension_loaded('mbstring')],
            ['name' => 'OpenSSL', 'ok' => extension_loaded('openssl')],
            ['name' => 'JSON', 'ok' => extension_loaded('json')],
            ['name' => 'Redis', 'ok' => extension_loaded('redis')],
            ['name' => 'pcntl（队列必需）', 'ok' => extension_loaded('pcntl')],
            ['name' => 'storage/ 可写', 'ok' => is_writable(storage_path())],
            ['name' => 'database/ 可写', 'ok' => is_writable(database_path())],
        ];

        if (!$this->isPreconfigured()) {
            $checks[] = ['name' => 'PDO SQLite', 'ok' => extension_loaded('pdo_sqlite')];
        }

        return $checks;
    }

    private function setEnv(string $key, ?string $value): void
    {
        $value = str_replace(["\n", "\r", '"'], '', $value ?? '');
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;

        $envPath = base_path('.env');
        $content = file_get_contents($envPath);
        $escaped = str_contains($value, ' ') || str_contains($value, '#') ? "\"$value\"" : $value;

        if (preg_match("/^{$key}=.*/m", $content)) {
            $content = preg_replace("/^{$key}=.*/m", "{$key}={$escaped}", $content);
        } else {
            $content .= "\n{$key}={$escaped}";
        }

        file_put_contents($envPath, $content);
    }

    private function ensureAppKey(): void
    {
        $key = (string) env('APP_KEY', '');
        if ($key !== '' && $key !== 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=') {
            return;
        }

        Artisan::call('key:generate', ['--force' => true]);
    }
}
