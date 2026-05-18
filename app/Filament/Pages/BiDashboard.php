<?php

namespace App\Filament\Pages;

use BackedEnum;
use UnitEnum;
use App\Models\AgentTransaction;
use App\Models\CommissionLog;
use App\Models\RedeemCode;
use App\Models\UsageLog;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

class BiDashboard extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-chart-bar-square';
    protected static ?string $navigationLabel = 'BI 数据';
    protected static string | UnitEnum | null $navigationGroup = '数据分析';
    protected static ?int $navigationSort = 0;
    protected string $view = 'filament.pages.bi-dashboard';
    protected static ?string $title = 'BI 数据看板';

    public function getSummaryStats(): array
    {
        $totalUsers = User::count();
        $totalAgents = User::where('role', 'agent')->count();
        $totalDistributors = User::where('is_distributor', true)->count();
        $monthCredits = UsageLog::where('created_at', '>=', now()->subDays(30))->sum('cost_credits');
        $monthCommission = CommissionLog::where('created_at', '>=', now()->subDays(30))->sum('credits');
        $pendingWithdrawals = WithdrawalRequest::where('status', 'pending')->count();

        return compact('totalUsers', 'totalAgents', 'totalDistributors', 'monthCredits', 'monthCommission', 'pendingWithdrawals');
    }

    public function getFinanceStats(): array
    {
        $since30 = now()->subDays(30);

        $totalRecharge = AgentTransaction::where('type', 'recharge')
            ->where('created_at', '>=', $since30)->sum('credits');
        $totalRechargeBalance = AgentTransaction::where('type', 'recharge')
            ->where('created_at', '>=', $since30)->sum('balance');

        $withdrawApproved = WithdrawalRequest::where('status', 'approved')
            ->where('created_at', '>=', $since30)->sum('amount');
        $withdrawPending = WithdrawalRequest::where('status', 'pending')->sum('amount');

        $creditsIssued = (int) RedeemCode::where('status', 'used')
            ->where('used_at', '>=', $since30)->sum('credits');
        $creditsConsumed = (int) UsageLog::where('created_at', '>=', $since30)->sum('cost_credits');
        $balanceConsumed = UsageLog::where('created_at', '>=', $since30)->sum('cost_balance');

        $totalUserCredits = (int) User::sum('credits');
        $totalUserBalance = User::sum('balance');

        $activeUsers30 = UsageLog::where('created_at', '>=', $since30)
            ->distinct('user_id')->count('user_id');
        $arpu = $activeUsers30 > 0 ? round($creditsConsumed / $activeUsers30, 1) : 0;

        return compact(
            'totalRecharge', 'totalRechargeBalance',
            'withdrawApproved', 'withdrawPending',
            'creditsIssued', 'creditsConsumed', 'balanceConsumed',
            'totalUserCredits', 'totalUserBalance',
            'activeUsers30', 'arpu'
        );
    }

    public function getRechargeChartData(): array
    {
        $labels = [];
        $credits = [];
        $balance = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('m/d');
            $credits[] = (int) AgentTransaction::where('type', 'recharge')
                ->whereDate('created_at', $date)->sum('credits');
            $balance[] = (float) AgentTransaction::where('type', 'recharge')
                ->whereDate('created_at', $date)->sum('balance');
        }
        return ['labels' => $labels, 'credits' => $credits, 'balance' => $balance];
    }

    public function getUserGrowthData(): array
    {
        $labels = [];
        $data = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('m/d');
            $data[] = User::whereDate('created_at', $date)->count();
        }
        return ['labels' => $labels, 'data' => $data];
    }

    public function getRevenueData(): array
    {
        $labels = [];
        $data = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $labels[] = now()->subDays($i)->format('m/d');
            $data[] = (int) UsageLog::whereDate('created_at', $date)->sum('cost_credits');
        }
        return ['labels' => $labels, 'data' => $data];
    }

    public function getAgentLeaderboard(): array
    {
        return User::where('role', 'agent')
            ->select('users.id', 'users.name', 'users.credits')
            ->withCount('children')
            ->get()
            ->map(function ($agent) {
                $childIds = $agent->children()->pluck('id');
                $agent->total_consumed = UsageLog::whereIn('user_id', $childIds)
                    ->where('created_at', '>=', now()->subDays(30))
                    ->sum('cost_credits');
                return $agent;
            })
            ->sortByDesc('total_consumed')
            ->take(10)
            ->values()
            ->toArray();
    }

    public function getDistributorLeaderboard(): array
    {
        return CommissionLog::where('created_at', '>=', now()->subDays(30))
            ->select('user_id', DB::raw('SUM(credits) as total_commission'))
            ->groupBy('user_id')
            ->orderByDesc('total_commission')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'name' => User::find($row->user_id)?->name ?? '—',
                'total_commission' => (int) $row->total_commission,
            ])
            ->toArray();
    }
}
