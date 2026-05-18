<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['email' => '邮箱或密码错误'])->withInput();
        }

        if ($user->status !== 'active') {
            return back()->withErrors(['email' => '账号已被禁用'])->withInput();
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function showRegister(Request $request)
    {
        if ($invite = $request->query('invite')) {
            session(['invite_code' => $invite]);
        }
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'name' => 'required|string|max:50',
            'invite_code' => 'nullable|string|max:32',
        ]);

        $parentId = null;
        $inviteCode = $data['invite_code'] ?? session('invite_code');
        if (!empty($inviteCode)) {
            $inviter = User::where('invite_code', $inviteCode)
                ->where('status', 'active')
                ->where(function ($q) {
                    $q->whereIn('role', ['agent', 'admin'])
                      ->orWhere('is_distributor', true);
                })
                ->first();
            if ($inviter) {
                $parentId = $inviter->id;
            }
        }
        session()->forget('invite_code');

        $initCredits = (int) SiteSetting::get('register_gift_credits', 5);
        $initBalance = (float) SiteSetting::get('register_gift_balance', 0);

        $user = new User([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'parent_id' => $parentId,
        ]);
        $user->role = 'user';
        $user->status = 'active';
        $user->balance = $initBalance;
        $user->credits = $initCredits;
        $user->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
