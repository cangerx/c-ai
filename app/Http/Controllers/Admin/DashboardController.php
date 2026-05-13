<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiChannel;
use App\Models\RedeemCode;
use App\Models\UsageLog;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'users' => User::count(),
            'today_tasks' => UsageLog::whereDate('created_at', today())->count(),
            'redeem_codes' => RedeemCode::where('status', 'unused')->count(),
            'channels' => AiChannel::where('status', 'active')->count(),
        ];

        $recentUsers = User::latest()->limit(10)->get();
        $recentUsage = UsageLog::with('user')->latest()->limit(10)->get();

        return view('admin.dashboard', compact('stats', 'recentUsers', 'recentUsage'));
    }
}
