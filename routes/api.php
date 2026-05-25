<?php

use App\Http\Controllers\Api\AsyncCallbackController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RedeemController;
use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WithdrawalController;
use App\Apps\ImageGen\Controllers\GalleryController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['ok' => true]));

// ---- Billing public routes ----
Route::get('/billing/packages', [BillingController::class, 'packages']);
Route::post('/payment/notify/{provider}', [BillingController::class, 'notify']);

Route::middleware('throttle:30,1')->get('/download', [DownloadController::class, 'proxy']);
Route::middleware('throttle:60,1')->get('/download-url', [DownloadController::class, 'presign']);

// async-oo provider 上游回调入口（仅靠 64-hex 一次性 token 鉴权，不走 sanctum）
Route::post('/channels/async-oo/callback/{token}', AsyncCallbackController::class)
    ->where('token', '[a-f0-9]{64}');

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/templates', [TemplateController::class, 'index']);
    Route::get('/templates/{template}', [TemplateController::class, 'show']);
    Route::post('/templates/{template}/build', [TemplateController::class, 'build']);
});

Route::get('/config', function (\Illuminate\Http\Request $request) {
    $agentSite = \App\Models\AgentSite::resolveForHost($request->getHost());
    $cacheKey = $agentSite
        ? 'api:config:agent:' . $agentSite->id . ':' . $agentSite->updated_at?->timestamp
        : 'api:config';

    return \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($agentSite) {
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

        $costPerGeneration = $agentSite?->cost_per_generation
            ? (int) $agentSite->cost_per_generation
            : (int) \App\Models\SiteSetting::get('billing_per_generation', 1);

        return [
            'site_name' => $agentSite?->site_name ?: \App\Models\SiteSetting::get('site_name', 'Visionary AI'),
            'site_description' => $agentSite?->seo_description ?: \App\Models\SiteSetting::get('site_description', ''),
            'site_keywords' => $agentSite?->seo_keywords ?: \App\Models\SiteSetting::get('site_keywords', ''),
            'prompt_tool_model' => \App\Models\SiteSetting::get('prompt_tool_model', 'gpt-5.4-mini'),
            'reverse_prompt_model' => \App\Models\SiteSetting::get('reverse_prompt_model', 'gpt-5.4-mini'),
            'cost_per_generation' => $costPerGeneration,
            'billing_rules' => $agentSite?->cost_per_generation ? [] : $billingRules,
            'announcements' => $announcements,
            'models' => $models,
            'login_methods' => [
                'github' => \App\Models\SiteSetting::get('login_github_enabled', '0') === '1',
                'wechat' => \App\Models\SiteSetting::get('login_wechat_enabled', '0') === '1',
            ],
            'footer_text' => $agentSite?->footer_text ?: \App\Models\SiteSetting::get('footer_text', ''),
            'footer_icp' => $agentSite?->footer_icp ?: \App\Models\SiteSetting::get('footer_icp', ''),
            'hero_title' => $agentSite?->hero_title ?: \App\Models\SiteSetting::get('hero_title', ''),
            'hero_subtitle' => $agentSite?->hero_subtitle ?: \App\Models\SiteSetting::get('hero_subtitle', ''),
            'active_theme' => \App\Models\SiteSetting::get('active_theme', 'default'),
        ];
    });
});

/**
 * 轻量端点：仅返回前端默认模板 key
 * 前端 SSR 在 cookie 缺失时调一次，避免拉取整个 /config
 */
Route::get('/site/theme', function () {
    return \Illuminate\Support\Facades\Cache::remember('api:site:theme', 60, function () {
        return ['active' => \App\Models\SiteSetting::get('active_theme', 'default')];
    });
});

Route::get('/explore', [GalleryController::class, 'index']);

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/send-code', [AuthController::class, 'sendCode'])->middleware('throttle:5,1');
Route::post('/login-code', [AuthController::class, 'loginCode'])->middleware('throttle:login');
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

    Route::post('/upload-presign', function (\Illuminate\Http\Request $request) {
        $request->validate([
            'mime_type' => 'required|string|in:image/png,image/jpeg,image/webp,image/gif',
        ]);
        $storage = app(\App\Services\ImageStorageService::class);
        $result = $storage->generatePresign($request->input('mime_type'));
        if (!$result) {
            return response()->json(['direct' => false]);
        }
        return response()->json($result);
    });

    Route::post('/upload-image', function (\Illuminate\Http\Request $request) {
        $request->validate(['image' => 'required|image|max:20480']);
        $file = $request->file('image');
        $storage = app(\App\Services\ImageStorageService::class);
        $purpose = \App\Services\StorageProfileService::PURPOSE_UPLOAD;
        try {
            $key = $storage->store(file_get_contents($file->getRealPath()), $file->getMimeType(), $purpose);
        } catch (\Throwable $e) {
            \Log::warning('upload image failed', [
                'user_id' => optional($request->user())->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => '参考图上传失败：' . $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'url' => $storage->url($key, $purpose),
            'key' => $key,
            'purpose' => $purpose,
            'expires_at' => now()->addDays((int) \App\Models\SiteSetting::get('storage_temp_ttl_days', 7))->toDateTimeString(),
        ]);
    });

    Route::post('/reverse-prompt', function (\Illuminate\Http\Request $request) {
        $data = $request->validate([
            'image_url' => 'required|string|max:20000000',
            'prompt' => 'nullable|string|max:4000',
        ]);

        try {
            $result = app(\App\Services\ReversePromptService::class)->analyze(
                $data['image_url'],
                $data['prompt'] ?? null
            );
            return response()->json($result);
        } catch (\Throwable $e) {
            \Log::warning('反推失败', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    })->middleware('throttle:20,1');

    Route::post('/prompt-tool', function (\Illuminate\Http\Request $request) {
        $data = $request->validate([
            'kind' => 'required|string|in:optimize,translate',
            'prompt' => 'required|string|max:8000',
        ]);

        $result = app(\App\Services\PromptToolService::class)->run(
            $data['kind'],
            $data['prompt']
        );

        return response()->json($result);
    })->middleware('throttle:30,1');

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

    // Billing (authenticated)
    Route::post('/billing/orders', [BillingController::class, 'createOrder']);
    Route::get('/billing/orders', [BillingController::class, 'userOrders']);
    Route::get('/billing/orders/{order_no}', [BillingController::class, 'showOrder']);
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
