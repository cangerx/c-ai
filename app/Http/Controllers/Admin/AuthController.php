<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check() && in_array(Auth::user()->role, ['admin', 'agent'])) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials, true)) {
            return back()->withErrors(['email' => '邮箱或密码错误'])->withInput();
        }

        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'agent'])) {
            Auth::logout();
            return back()->withErrors(['email' => '权限不足，仅管理员和代理商可登录后台'])->withInput();
        }

        if ($user->status !== 'active') {
            Auth::logout();
            return back()->withErrors(['email' => '账号已被禁用'])->withInput();
        }

        $request->session()->regenerate();
        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('admin.login');
    }
}
