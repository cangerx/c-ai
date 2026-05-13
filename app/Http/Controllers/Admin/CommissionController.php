<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Models\UsageLog;
use Illuminate\Http\Request;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $rate = (float) SiteSetting::get('agent_commission_rate', 0.10);

        if ($user->isAdmin()) {
            // Admin 看所有有 parent_id 的使用记录
            $logs = UsageLog::where('cost_balance', '>', 0)
                ->whereHas('user', fn($q) => $q->whereNotNull('parent_id'))
                ->with('user', 'user.parent')
                ->latest()
                ->paginate(20);
        } else {
            // 代理商只看自己下级的
            $childIds = $user->children()->pluck('id');
            $logs = UsageLog::whereIn('user_id', $childIds)
                ->where('cost_balance', '>', 0)
                ->with('user')
                ->latest()
                ->paginate(20);
        }

        return view('admin.commissions.index', compact('logs', 'rate'));
    }
}
