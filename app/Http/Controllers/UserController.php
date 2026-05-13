<?php

namespace App\Http\Controllers;

use App\Models\RedeemCode;
use App\Models\UsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        $recentLogs = UsageLog::where('user_id', $user->id)
            ->latest()
            ->limit(5)
            ->get();

        return view('user.dashboard', compact('user', 'recentLogs'));
    }

    public function profile()
    {
        $user = Auth::user();
        return view('user.profile', compact('user'));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'nickname' => 'required|string|max:50',
            'password' => 'nullable|string|min:6|confirmed',
        ]);

        $user = Auth::user();
        $user->nickname = $data['nickname'];

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return back()->with('success', '资料已更新');
    }

    public function usageHistory(Request $request)
    {
        $logs = UsageLog::where('user_id', Auth::id())
            ->latest()
            ->paginate(20);

        return view('user.usage', compact('logs'));
    }

    public function redeemPage()
    {
        return view('user.redeem');
    }

    public function redeem(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|size:32',
        ]);

        $user = Auth::user();

        try {
            DB::transaction(function () use ($data, $user) {
                $code = RedeemCode::where('code', $data['code'])
                    ->where('status', 'unused')
                    ->lockForUpdate()
                    ->first();

                if (!$code) {
                    throw new \Exception('兑换码无效或已使用');
                }

                if ($code->isExpired()) {
                    throw new \Exception('兑换码已过期');
                }

                $code->update([
                    'status' => 'used',
                    'used_by' => $user->id,
                    'used_at' => now(),
                ]);

                $user->increment('credits', $code->credits);
                $user->increment('balance', $code->balance);
            });
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage())->withInput();
        }

        return back()->with('success', '兑换成功！');
    }
}
