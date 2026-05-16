<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'name' => 'required|string|max:50',
            'invite_code' => 'nullable|string|max:32',
        ]);

        $parentId = null;
        if (!empty($data['invite_code'])) {
            $agent = User::where('invite_code', $data['invite_code'])
                ->whereIn('role', ['agent', 'admin'])
                ->where('status', 'active')
                ->first();
            if ($agent) {
                $parentId = $agent->id;
            }
        }

        $initCredits = (int) SiteSetting::get('register_gift_credits', 5);
        $initBalance = (float) SiteSetting::get('register_gift_balance', 0);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'user',
            'status' => 'active',
            'balance' => $initBalance,
            'credits' => $initCredits,
            'parent_id' => $parentId,
        ]);

        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['邮箱或密码错误'],
            ]);
        }

        if ($user->status !== 'active') {
            throw ValidationException::withMessages([
                'email' => ['账号已被禁用'],
            ]);
        }

        $token = $user->createToken('app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $this->userPayload($request->user()),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => '已退出登录']);
    }

    public function githubRedirect()
    {
        if (SiteSetting::get('login_github_enabled', '0') !== '1') {
            return response()->json(['message' => 'GitHub 登录未开启'], 403);
        }

        $clientId = SiteSetting::get('github_client_id', config('services.github.client_id'));
        $clientSecret = SiteSetting::get('github_client_secret', config('services.github.client_secret'));
        $redirect = config('services.github.redirect');

        config(['services.github.client_id' => $clientId, 'services.github.client_secret' => $clientSecret, 'services.github.redirect' => $redirect]);

        return response()->json([
            'url' => Socialite::driver('github')->stateless()->redirect()->getTargetUrl(),
        ]);
    }

    public function githubCallback(Request $request)
    {
        if (SiteSetting::get('login_github_enabled', '0') !== '1') {
            return redirect('/?auth_error=github_disabled');
        }

        $clientId = SiteSetting::get('github_client_id', config('services.github.client_id'));
        $clientSecret = SiteSetting::get('github_client_secret', config('services.github.client_secret'));

        config(['services.github.client_id' => $clientId, 'services.github.client_secret' => $clientSecret]);

        try {
            $ghUser = Socialite::driver('github')->stateless()->user();
        } catch (\Exception $e) {
            return redirect('/?auth_error=github_failed');
        }

        $user = User::where('github_id', $ghUser->getId())->first();

        if (!$user && $ghUser->getEmail()) {
            $user = User::where('email', $ghUser->getEmail())->first();
            if ($user) {
                $user->update(['github_id' => $ghUser->getId(), 'avatar' => $ghUser->getAvatar()]);
            }
        }

        if (!$user) {
            $initCredits = (int) SiteSetting::get('register_gift_credits', 5);
            $initBalance = (float) SiteSetting::get('register_gift_balance', 0);

            $user = User::create([
                'name' => $ghUser->getNickname() ?: $ghUser->getName() ?: 'GitHub User',
                'email' => $ghUser->getEmail() ?: $ghUser->getId() . '@github.oauth',
                'github_id' => $ghUser->getId(),
                'avatar' => $ghUser->getAvatar(),
                'role' => 'user',
                'status' => 'active',
                'credits' => $initCredits,
                'balance' => $initBalance,
            ]);
        }

        if ($user->status !== 'active') {
            return redirect('/?auth_error=account_disabled');
        }

        $token = $user->createToken('app')->plainTextToken;

        return redirect('/?token=' . $token);
    }

    public function wechatRedirect()
    {
        if (SiteSetting::get('login_wechat_enabled', '0') !== '1') {
            return response()->json(['message' => '微信登录未开启'], 403);
        }

        $appId = SiteSetting::get('wechat_appid', config('services.wechat.client_id'));
        $redirect = url(config('services.wechat.redirect'));
        $state = bin2hex(random_bytes(16));

        return response()->json([
            'appid' => $appId,
            'redirect_uri' => $redirect,
            'state' => $state,
        ]);
    }

    public function wechatCallback(Request $request)
    {
        if (SiteSetting::get('login_wechat_enabled', '0') !== '1') {
            return redirect('/?auth_error=wechat_disabled');
        }

        $code = $request->query('code');
        if (!$code) {
            return redirect('/?auth_error=wechat_failed');
        }

        $appId = SiteSetting::get('wechat_appid', config('services.wechat.client_id'));
        $secret = SiteSetting::get('wechat_secret', config('services.wechat.client_secret'));

        $tokenResp = \Illuminate\Support\Facades\Http::get('https://api.weixin.qq.com/sns/oauth2/access_token', [
            'appid' => $appId,
            'secret' => $secret,
            'code' => $code,
            'grant_type' => 'authorization_code',
        ])->json();

        if (empty($tokenResp['openid'])) {
            return redirect('/?auth_error=wechat_failed');
        }

        $userInfo = \Illuminate\Support\Facades\Http::get('https://api.weixin.qq.com/sns/userinfo', [
            'access_token' => $tokenResp['access_token'],
            'openid' => $tokenResp['openid'],
        ])->json();

        $openid = $tokenResp['openid'];
        $unionid = $tokenResp['unionid'] ?? $userInfo['unionid'] ?? null;
        $nickname = $userInfo['nickname'] ?? 'WeChat User';
        $avatar = $userInfo['headimgurl'] ?? null;

        $user = User::where('wechat_openid', $openid)->first();

        if (!$user && $unionid) {
            $user = User::where('wechat_unionid', $unionid)->first();
        }

        if (!$user) {
            $initCredits = (int) SiteSetting::get('register_gift_credits', 5);
            $initBalance = (float) SiteSetting::get('register_gift_balance', 0);

            $user = User::create([
                'name' => $nickname,
                'email' => $openid . '@wechat.oauth',
                'wechat_openid' => $openid,
                'wechat_unionid' => $unionid,
                'avatar' => $avatar,
                'role' => 'user',
                'status' => 'active',
                'credits' => $initCredits,
                'balance' => $initBalance,
            ]);
        } else {
            $user->update(array_filter(['avatar' => $avatar, 'wechat_unionid' => $unionid]));
        }

        if ($user->status !== 'active') {
            return redirect('/?auth_error=account_disabled');
        }

        $token = $user->createToken('app')->plainTextToken;

        return redirect('/?token=' . $token);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->input('email'))->first();
        if (!$user) {
            return response()->json(['message' => '如果该邮箱已注册，重置链接将发送到您的邮箱']);
        }

        $token = Str::random(64);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($token), 'created_at' => now()]
        );

        $resetUrl = url('/reset-password?token=' . $token . '&email=' . urlencode($user->email));
        Mail::raw("您正在重置 CANG-AI 账号密码，请点击以下链接完成重置（30分钟内有效）：\n\n{$resetUrl}\n\n如非本人操作，请忽略此邮件。", function ($msg) use ($user) {
            $msg->to($user->email)->subject('重置密码 - CANG-AI');
        });

        return response()->json(['message' => '如果该邮箱已注册，重置链接将发送到您的邮箱']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string',
            'email' => 'required|email',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $record = DB::table('password_reset_tokens')->where('email', $data['email'])->first();
        if (!$record || !Hash::check($data['token'], $record->token)) {
            return response()->json(['message' => '重置链接无效或已过期'], 422);
        }

        if (now()->diffInMinutes($record->created_at) > 30) {
            DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
            return response()->json(['message' => '重置链接已过期，请重新申请'], 422);
        }

        $user = User::where('email', $data['email'])->first();
        if (!$user) {
            return response()->json(['message' => '用户不存在'], 404);
        }

        $user->update(['password' => Hash::make($data['password'])]);
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();
        $user->tokens()->delete();

        return response()->json(['message' => '密码重置成功，请重新登录']);
    }

    protected function userPayload(User $user): array
    {
        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nickname' => $user->nickname,
            'role' => $user->role,
            'credits' => $user->credits,
            'balance' => $user->balance,
            'is_distributor' => $user->is_distributor,
            'total_consumed_credits' => $user->total_consumed_credits,
            'created_at' => $user->created_at?->toIso8601String(),
        ];

        if ($user->is_distributor) {
            $payload['invite_code'] = $user->ensureInviteCode();
            $payload['commission_credits'] = $user->commission_credits;
        }

        return $payload;
    }
}
