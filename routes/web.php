<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\InstallController;
use App\Http\Controllers\SubSiteController;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Route;

// 首页：读取 index.html 并注入后台 SEO 设置
Route::get('/', function () {
    if (!file_exists(storage_path('installed'))) {
        return redirect('/install');
    }
    if (app()->bound('agent_site')) {
        return app(SubSiteController::class)->index();
    }
    $path = public_path('index.html');
    if (!is_file($path)) {
        return redirect('/admin');
    }

    $html = file_get_contents($path);

    $siteName    = SiteSetting::get('site_name');
    $description = SiteSetting::get('site_description');
    $keywords    = SiteSetting::get('site_keywords');

    if ($siteName) {
        $safe = e($siteName);
        $html = preg_replace_callback('/<title>[^<]*<\/title>/', fn () => '<title>' . $safe . '</title>', $html);
        $html = preg_replace_callback('/(<meta\s+property="og:title"\s+content=")[^"]*(")/i', fn ($m) => $m[1] . $safe . $m[2], $html);
        $html = preg_replace_callback('/(<meta\s+name="twitter:title"\s+content=")[^"]*(")/i', fn ($m) => $m[1] . $safe . $m[2], $html);
        $html = preg_replace_callback('/<h1>[^<]*<\/h1>/', fn () => '<h1>' . $safe . '</h1>', $html);
    }
    if ($description) {
        $safe = e($description);
        $html = preg_replace_callback('/(<meta\s+name="description"\s+content=")[^"]*(")/i', fn ($m) => $m[1] . $safe . $m[2], $html);
        $html = preg_replace_callback('/(<meta\s+property="og:description"\s+content=")[^"]*(")/i', fn ($m) => $m[1] . $safe . $m[2], $html);
        $html = preg_replace_callback('/(<meta\s+name="twitter:description"\s+content=")[^"]*(")/i', fn ($m) => $m[1] . $safe . $m[2], $html);
    }
    if ($keywords) {
        $html = preg_replace_callback('/(<meta\s+name="keywords"\s+content=")[^"]*(")/i', fn ($m) => $m[1] . e($keywords) . $m[2], $html);
    }

    // 注入首页可视化设置供前端 JS 使用
    $siteConfig = json_encode([
        'name' => $siteName,
        'hero_title' => SiteSetting::get('hero_title'),
        'hero_subtitle' => SiteSetting::get('hero_subtitle'),
        'hero_bg_url' => SiteSetting::get('hero_bg_url'),
        'hero_bg_color' => SiteSetting::get('hero_bg_color'),
        'footer_text' => SiteSetting::get('footer_text'),
        'footer_icp' => SiteSetting::get('footer_icp'),
    ]);
    $html = str_replace('</head>', "<script>window.__SITE_CONFIG__={$siteConfig};</script></head>", $html);

    return response($html)->header('Content-Type', 'text/html');
});

Route::get('/terms', fn () => response()->file(public_path('terms.html')));
Route::get('/privacy', fn () => response()->file(public_path('privacy.html')));
Route::get('/reset-password', fn () => view('auth.reset-password'));

Route::get('/explore', [\App\Apps\ImageGen\Controllers\GalleryController::class, 'index'])->name('explore');
Route::get('/explore/templates', [\App\Apps\ImageGen\Controllers\GalleryController::class, 'templates'])->name('explore.templates');
Route::get('/explore/templates/{id}/use', [\App\Apps\ImageGen\Controllers\GalleryController::class, 'useTemplate'])->whereNumber('id')->name('explore.templates.use');
Route::get('/pricing', function () {
    if (app()->bound('agent_site')) {
        return app(SubSiteController::class)->pricing();
    }
    return app(\App\Http\Controllers\PricingController::class)->index();
})->name('pricing');

// 认证路由
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.submit');
Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
Route::post('/register', [AuthController::class, 'register'])->name('register.submit');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

