<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentTransaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubUserController extends Controller
{
    public function index(Request $request)
    {
        $agent = $request->user();
        $users = $agent->children()->latest()->paginate(20);
        return view('agent.sub-users', compact('users'));
    }

    public function recharge(Request $request, User $user)
    {
        $agent = $request->user();

        if ($user->parent_id !== $agent->id) {
            abort(403, '无权操作');
        }

        $data = $request->validate([
            'credits' => 'nullable|integer|min:0|max:9999',
            'balance' => 'nullable|numeric|min:0|max:99999',
        ]);

        $credits = $data['credits'] ?? 0;
        $balance = (float) ($data['balance'] ?? 0);

        if ($credits <= 0 && $balance <= 0) {
            return back()->withErrors(['credits' => '请输入充值数量']);
        }

        // Check agent has enough
        if ($credits > 0 && $agent->credits < $credits) {
            return back()->withErrors(['credits' => '您的积分余额不足']);
        }
        if ($balance > 0 && $agent->balance < $balance) {
            return back()->withErrors(['balance' => '您的余额不足']);
        }

        DB::transaction(function () use ($agent, $user, $credits, $balance) {
            if ($credits > 0) {
                $agent->decrement('credits', $credits);
                $user->increment('credits', $credits);
            }
            if ($balance > 0) {
                $agent->decrement('balance', $balance);
                $user->increment('balance', $balance);
            }

            $agent->refresh();
            AgentTransaction::create([
                'user_id' => $agent->id,
                'type' => 'recharge',
                'credits' => -$credits,
                'balance' => -$balance,
                'credits_after' => $agent->credits,
                'balance_after' => $agent->balance,
                'description' => "为用户 {$user->name} 充值 {$credits}积分 ¥{$balance}",
            ]);
        });

        return back()->with('success', "已为 {$user->name} 充值");
    }
}
