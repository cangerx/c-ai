<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentTransaction;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    public function index(Request $request)
    {
        $agent = auth()->user();
        $childIds = $agent->children()->pluck('id');

        $todayUsers = $agent->children()->whereDate('created_at', today())->count();
        $todayCredits = UsageLog::whereIn('user_id', $childIds)->whereDate('created_at', today())->sum('cost_credits');
        $todayCommission = AgentTransaction::where('user_id', $agent->id)->where('type', 'commission')->whereDate('created_at', today())->sum('credits');

        $weekUsers = $agent->children()->where('created_at', '>=', now()->subDays(7))->count();
        $weekCredits = UsageLog::whereIn('user_id', $childIds)->where('created_at', '>=', now()->subDays(7))->sum('cost_credits');
        $weekCommission = AgentTransaction::where('user_id', $agent->id)->where('type', 'commission')->where('created_at', '>=', now()->subDays(7))->sum('credits');

        $monthUsers = $agent->children()->where('created_at', '>=', now()->subDays(30))->count();
        $monthCredits = UsageLog::whereIn('user_id', $childIds)->where('created_at', '>=', now()->subDays(30))->sum('cost_credits');
        $monthCommission = AgentTransaction::where('user_id', $agent->id)->where('type', 'commission')->where('created_at', '>=', now()->subDays(30))->sum('credits');

        // Chart data — batch queries
        $since = now()->subDays(29)->startOfDay();

        $usersByDay = $agent->children()->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')
            ->groupByRaw('DATE(created_at)')->pluck('c', 'd');

        $creditsByDay = UsageLog::whereIn('user_id', $childIds)->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, SUM(cost_credits) as c')
            ->groupByRaw('DATE(created_at)')->pluck('c', 'd');

        $chartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $chartData[] = [
                'date' => now()->subDays($i)->format('m/d'),
                'users' => (int) ($usersByDay[$date] ?? 0),
                'credits' => (int) ($creditsByDay[$date] ?? 0),
            ];
        }

        return view('agent.statistics', compact(
            'todayUsers', 'todayCredits', 'todayCommission',
            'weekUsers', 'weekCredits', 'weekCommission',
            'monthUsers', 'monthCredits', 'monthCommission',
            'chartData'
        ));
    }
}
