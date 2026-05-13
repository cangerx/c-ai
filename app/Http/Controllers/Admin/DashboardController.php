<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiChannel;
use App\Models\RedeemCode;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

        $dailyTasks = UsageLog::select(
            DB::raw("DATE(created_at) as date"),
            DB::raw("COUNT(*) as total")
        )
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        $chartLabels = [];
        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = now()->subDays($i)->format('m/d');
            $chartData[] = $dailyTasks[$date] ?? 0;
        }

        return view('admin.dashboard', compact('stats', 'recentUsers', 'recentUsage', 'chartLabels', 'chartData'));
    }
}
