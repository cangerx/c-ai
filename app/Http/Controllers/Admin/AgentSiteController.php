<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AgentLevel;
use App\Models\AgentSite;
use App\Models\AgentTransaction;
use App\Models\UsageLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AgentSiteController extends Controller
{
    public function index(Request $request)
    {
        $query = AgentSite::with('agent');

        if ($search = $request->get('q')) {
            $s = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->where(function ($q) use ($s) {
                $q->where('site_name', 'like', "%{$s}%")
                  ->orWhere('slug', 'like', "%{$s}%")
                  ->orWhere('custom_domain', 'like', "%{$s}%")
                  ->orWhereHas('agent', fn($q2) => $q2->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"));
            });
        }

        if ($status = $request->get('status')) {
            if ($status === 'disabled') {
                $query->where('is_active', false);
            } else {
                $query->where('status', $status);
            }
        }

        // Summary stats
        $totalSites = AgentSite::count();
        $pendingSites = AgentSite::where('status', 'pending')->count();
        $activeSites = AgentSite::where('status', 'approved')->where('is_active', true)->count();
        $totalAgents = User::where('role', 'agent')->count();

        $sites = $query->latest()->paginate(20)->withQueryString();

        // 为列表中每个分站注入统计数据
        $siteAgentIds = $sites->pluck('user_id')->unique();
        $childCounts = User::whereIn('parent_id', $siteAgentIds)
            ->selectRaw('parent_id, COUNT(*) as cnt')
            ->groupBy('parent_id')->pluck('cnt', 'parent_id');

        $agentLevels = AgentLevel::ordered()->get();

        return view('admin.agent-sites.index', compact(
            'sites', 'totalSites', 'pendingSites', 'activeSites', 'totalAgents',
            'childCounts', 'agentLevels'
        ));
    }

    public function show(AgentSite $agentSite)
    {
        $agentSite->load('agent');
        $agent = $agentSite->agent;
        $childIds = User::where('parent_id', $agent->id)->pluck('id');

        // 数据统计
        $stats = [
            'sub_users' => $childIds->count(),
            'today_users' => User::where('parent_id', $agent->id)->whereDate('created_at', today())->count(),
            'total_usage' => UsageLog::whereIn('user_id', $childIds)->count(),
            'today_usage' => UsageLog::whereIn('user_id', $childIds)->whereDate('created_at', today())->count(),
            'total_credits' => UsageLog::whereIn('user_id', $childIds)->sum('cost_credits'),
            'total_commission' => AgentTransaction::where('user_id', $agent->id)->where('type', 'commission')->sum('credits'),
            'total_recharged' => $agent->total_recharged,
            'balance' => $agent->balance,
            'credits' => $agent->credits,
        ];

        // 30天趋势
        $since = now()->subDays(29)->startOfDay();
        $usersByDay = User::where('parent_id', $agent->id)->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupByRaw('DATE(created_at)')->pluck('c', 'd');
        $usageByDay = UsageLog::whereIn('user_id', $childIds)->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupByRaw('DATE(created_at)')->pluck('c', 'd');

        $chartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $chartData[] = [
                'date' => now()->subDays($i)->format('m/d'),
                'users' => (int) ($usersByDay[$date] ?? 0),
                'usage' => (int) ($usageByDay[$date] ?? 0),
            ];
        }

        return view('admin.agent-sites.show', compact('agentSite', 'agent', 'stats', 'chartData'));
    }

    public function edit(AgentSite $agentSite)
    {
        $agentSite->load('agent');
        $agentLevels = AgentLevel::ordered()->get();
        return view('admin.agent-sites.edit', compact('agentSite', 'agentLevels'));
    }

    public function update(Request $request, AgentSite $agentSite)
    {
        $data = $request->validate([
            'site_name' => 'required|string|max:100',
            'custom_domain' => 'nullable|string|max:255',
            'theme_color' => 'nullable|string|max:7',
            'cost_per_generation' => 'nullable|integer|min:1',
            'commission_rate' => 'nullable|integer|min:0|max:100',
            'is_active' => 'boolean',
        ]);

        // 更新代理商等级
        if ($request->has('agent_level_id')) {
            $agentSite->agent->update(['agent_level_id' => $request->input('agent_level_id')]);
        }

        $agentSite->update($data);
        return redirect()->route('admin.agent-sites.index')->with('success', '分站已更新');
    }

    public function approve(AgentSite $agentSite)
    {
        $agentSite->update([
            'status' => 'approved',
            'is_active' => true,
            'reject_reason' => null,
            'approved_at' => now(),
        ]);
        return back()->with('success', "分站「{$agentSite->site_name}」已审核通过");
    }

    public function reject(Request $request, AgentSite $agentSite)
    {
        $request->validate(['reject_reason' => 'required|string|max:500']);
        $agentSite->update([
            'status' => 'rejected',
            'is_active' => false,
            'reject_reason' => $request->input('reject_reason'),
        ]);
        return back()->with('success', "分站「{$agentSite->site_name}」已拒绝");
    }

    public function toggle(AgentSite $agentSite)
    {
        $agentSite->update(['is_active' => !$agentSite->is_active]);
        return back()->with('success', $agentSite->is_active ? '已启用' : '已禁用');
    }

    public function destroy(AgentSite $agentSite)
    {
        $agentSite->delete();
        return back()->with('success', '分站已删除');
    }

    // 批量操作
    public function batch(Request $request)
    {
        $request->validate([
            'action' => 'required|in:enable,disable,delete',
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:agent_sites,id',
        ]);

        $ids = $request->input('ids');
        $action = $request->input('action');

        switch ($action) {
            case 'enable':
                AgentSite::whereIn('id', $ids)->update(['is_active' => true]);
                $msg = count($ids) . ' 个分站已启用';
                break;
            case 'disable':
                AgentSite::whereIn('id', $ids)->update(['is_active' => false]);
                $msg = count($ids) . ' 个分站已禁用';
                break;
            case 'delete':
                AgentSite::whereIn('id', $ids)->delete();
                $msg = count($ids) . ' 个分站已删除';
                break;
        }

        return back()->with('success', $msg);
    }

    // 代理商等级管理
    public function levels()
    {
        $levels = AgentLevel::ordered()->get();
        $agentCountByLevel = User::where('role', 'agent')
            ->whereNotNull('agent_level_id')
            ->selectRaw('agent_level_id, COUNT(*) as cnt')
            ->groupBy('agent_level_id')->pluck('cnt', 'agent_level_id');

        return view('admin.agent-sites.levels', compact('levels', 'agentCountByLevel'));
    }

    public function levelStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'min_recharge' => 'required|numeric|min:0',
            'price_per_credit' => 'required|numeric|min:0.0001|max:99',
            'sort_order' => 'nullable|integer',
        ]);
        AgentLevel::create($data);
        return back()->with('success', '等级已创建');
    }

    public function levelUpdate(Request $request, AgentLevel $agentLevel)
    {
        $data = $request->validate([
            'name' => 'required|string|max:50',
            'min_recharge' => 'required|numeric|min:0',
            'price_per_credit' => 'required|numeric|min:0.0001|max:99',
            'sort_order' => 'nullable|integer',
        ]);
        $agentLevel->update($data);
        return back()->with('success', '等级已更新');
    }

    public function levelDestroy(AgentLevel $agentLevel)
    {
        User::where('agent_level_id', $agentLevel->id)->update(['agent_level_id' => null]);
        $agentLevel->delete();
        return back()->with('success', '等级已删除');
    }
}
