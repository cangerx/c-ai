<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\UsageLog;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $agent = $request->user();
        $agent->ensureInviteCode();

        $childIds = $agent->children()->pluck('id');

        $stats = [
            'sub_users' => $childIds->count(),
            'today_usage' => UsageLog::whereIn('user_id', $childIds)->whereDate('created_at', today())->count(),
            'total_usage' => UsageLog::whereIn('user_id', $childIds)->count(),
            'commission' => $agent->commission_balance,
        ];

        $recentUsers = $agent->children()->latest()->limit(10)->get();
        $recentUsage = UsageLog::with('user')->whereIn('user_id', $childIds)->latest()->limit(10)->get();

        return view('agent.dashboard', compact('agent', 'stats', 'recentUsers', 'recentUsage'));
    }
}
