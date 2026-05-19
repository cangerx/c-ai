<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RedeemController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['ok' => true]));

Route::middleware('throttle:30,1')->get('/download', function (\Illuminate\Http\Request $request) {
    $url = $request->query('url', '');
    if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
        abort(400, 'Invalid URL');
    }
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], true)) {
        abort(400, 'Invalid URL');
    }
    $host = parse_url($url, PHP_URL_HOST) ?: '';
    $ip = gethostbyname($host);
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        abort(400, 'Invalid URL');
    }
    $appHost = parse_url(config('app.url'), PHP_URL_HOST) ?: '';
    $storageUrl = \App\Models\SiteSetting::get('storage_url', '');
    $storageHost = $storageUrl ? (parse_url($storageUrl, PHP_URL_HOST) ?: '') : '';
    $allowed = array_filter([$appHost, $storageHost]);
    $isAllowed = preg_match('/^(cdn\d*\.dmiapi\.com|cdn\d*\.duomiapi\.com)$/', $host)
        || in_array($host, $allowed, true);
    if (!$isAllowed) {
        abort(400, 'Invalid URL');
    }
    $response = \Illuminate\Support\Facades\Http::timeout(30)->get($url);
    if (!$response->successful()) abort(502);
    $contentType = $response->header('Content-Type') ?: 'image/png';
    $filename = preg_replace('/[^\w.\-]/', '_', basename(parse_url($url, PHP_URL_PATH)) ?: 'image.png');
    return response($response->body(), 200, [
        'Content-Type' => $contentType,
        'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        'Cache-Control' => 'public, max-age=86400',
    ]);
});

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/templates', [TemplateController::class, 'index']);
    Route::get('/templates/{template}', [TemplateController::class, 'show']);
    Route::post('/templates/{template}/build', [TemplateController::class, 'build']);
});

Route::get('/config', function () {
    $announcements = \App\Models\Announcement::where('enabled', true)
        ->orderBy('sort')->orderByDesc('id')
        ->pluck('content', 'url')
        ->map(fn($content, $url) => $url ? "{$content} · <a href='{$url}' target='_blank'>了解更多 →</a>" : $content)
        ->values()->all();

    $models = \Illuminate\Support\Facades\DB::table('ai_models')
        ->where('type', 'image')
        ->where('is_active', true)
        ->get()
        ->map(function ($m) {
            $item = ['id' => $m->model_id, 'name' => $m->display_name];
            $config = json_decode($m->config, true);
            if (!empty($config['sizes'])) $item['sizes'] = $config['sizes'];
            if (!empty($config['qualities'])) $item['qualities'] = $config['qualities'];
            return $item;
        })
        ->values()->all();

    $billingRules = \App\Models\BillingRule::all()->map(fn($r) => [
        'app' => $r->app_name,
        'model' => $r->model_pattern,
        'quality' => $r->quality ?: '*',
        'credits' => $r->cost_credits,
    ])->values()->all();

    return response()->json([
        'prompt_tool_model' => \App\Models\SiteSetting::get('prompt_tool_model', 'gpt-5.4-mini'),
        'reverse_prompt_model' => \App\Models\SiteSetting::get('reverse_prompt_model', 'gpt-5.4-mini'),
        'cost_per_generation' => (int) \App\Models\SiteSetting::get('billing_per_generation', 1),
        'billing_rules' => $billingRules,
        'announcements' => $announcements,
        'models' => $models,
        'login_methods' => [
            'github' => \App\Models\SiteSetting::get('login_github_enabled', '0') === '1',
            'wechat' => \App\Models\SiteSetting::get('login_wechat_enabled', '0') === '1',
        ],
    ]);
});

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:login');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::get('/auth/github', [AuthController::class, 'githubRedirect']);
Route::get('/auth/github/callback', [AuthController::class, 'githubCallback']);
Route::get('/auth/wechat', [AuthController::class, 'wechatRedirect']);
Route::get('/auth/wechat/callback', [AuthController::class, 'wechatCallback']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/redeem', [RedeemController::class, 'redeem'])->middleware('throttle:10,1');

    Route::post('/distributor/apply', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        if ($user->is_distributor) {
            return response()->json(['message' => '您已是分销者'], 200);
        }
        $threshold = (int) \App\Models\SiteSetting::get('distributor_threshold', 100);
        if ($user->total_consumed_credits < $threshold) {
            return response()->json(['message' => "累计消费需达到 {$threshold} 积分才可申请"], 403);
        }
        $user->is_distributor = true;
        $user->ensureInviteCode();
        $user->save();
        return response()->json(['message' => '分销开通成功', 'invite_code' => $user->invite_code]);
    });

    Route::post('/upload-image', function (\Illuminate\Http\Request $request) {
        $request->validate(['image' => 'required|image|max:10240']);
        $file = $request->file('image');
        $storage = app(\App\Services\ImageStorageService::class);
        $key = $storage->store(file_get_contents($file->getRealPath()), $file->getMimeType());
        return response()->json(['url' => $storage->url($key)]);
    });

    Route::get('/distributor/invites', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        if (!$user->is_distributor) {
            return response()->json(['error' => '非分销者'], 403);
        }
        $invites = \App\Models\User::where('parent_id', $user->id)
            ->select('id', 'nickname', 'name', 'email', 'created_at', 'total_consumed_credits')
            ->orderByDesc('created_at')
            ->paginate(20);
        return response()->json($invites);
    });

    Route::get('/distributor/commissions', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        if (!$user->is_distributor) {
            return response()->json(['error' => '非分销者'], 403);
        }
        $logs = \App\Models\CommissionLog::where('user_id', $user->id)
            ->with('fromUser:id,nickname,name,email')
            ->orderByDesc('created_at')
            ->paginate(20);
        return response()->json($logs);
    });

    Route::put('/me', [UserController::class, 'updateMe']);
    Route::get('/usage', [UserController::class, 'usage']);
    Route::get('/tasks', [UserController::class, 'tasks']);
    Route::delete('/tasks/{taskId}', [UserController::class, 'deleteTask']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);

    Route::get('/withdrawals', [WithdrawalController::class, 'index']);
    Route::post('/withdrawals', [WithdrawalController::class, 'store']);

    Route::post('/agent/apply', function (\Illuminate\Http\Request $request) {
        $enabled = filter_var(\App\Models\SiteSetting::get('agent_apply_enabled', false), FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return response()->json(['message' => '代理申请暂未开放'], 403);
        }
        $user = $request->user();
        if ($user->role === 'agent' || $user->role === 'admin') {
            return response()->json(['message' => '您已是代理商']);
        }
        if (\App\Models\AgentSite::where('user_id', $user->id)->exists()) {
            return response()->json(['message' => '您已提交过申请，请等待审核']);
        }
        $request->validate(['site_name' => 'required|string|max:100']);

        $user->ensureInviteCode();

        \App\Models\AgentSite::create([
            'user_id' => $user->id,
            'site_name' => $request->input('site_name'),
            'slug' => $user->invite_code,
            'subdomain' => $user->invite_code,
            'status' => 'pending',
            'is_active' => false,
        ]);

        return response()->json(['message' => '代理申请已提交，等待管理员审核']);
    });

    Route::get('/agent/apply-status', function (\Illuminate\Http\Request $request) {
        $enabled = filter_var(\App\Models\SiteSetting::get('agent_apply_enabled', false), FILTER_VALIDATE_BOOLEAN);
        $user = $request->user();
        $site = \App\Models\AgentSite::where('user_id', $user->id)->first();
        return response()->json([
            'apply_enabled' => $enabled,
            'is_agent' => in_array($user->role, ['agent', 'admin']),
            'site_status' => $site?->status,
        ]);
    });
});
