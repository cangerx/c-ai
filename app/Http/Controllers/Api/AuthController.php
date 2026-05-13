<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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

    protected function userPayload(User $user): array
    {
        $payload = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'nickname' => $user->nickname,
            'role' => $user->role,
            'balance' => $user->balance,
            'credits' => $user->credits,
            'created_at' => $user->created_at?->toIso8601String(),
        ];

        if (in_array($user->role, ['agent', 'admin'])) {
            $payload['invite_code'] = $user->ensureInviteCode();
        }

        return $payload;
    }
}
