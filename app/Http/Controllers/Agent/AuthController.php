<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('agent.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($data + ['status' => 'active'], true)) {
            return back()->withErrors(['email' => '邮箱或密码错误'])->withInput();
        }

        $user = Auth::user();
        if (!in_array($user->role, ['agent', 'admin'])) {
            Auth::logout();
            return back()->withErrors(['email' => '该账号不是代理角色'])->withInput();
        }

        $request->session()->regenerate();
        return redirect()->route('agent.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('agent.login');
    }
}
