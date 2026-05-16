<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentTransaction;
use App\Models\RedeemCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RedeemCodeController extends Controller
{
    public function index(Request $request)
    {
        $codes = RedeemCode::where('created_by', auth()->id())
            ->latest()
            ->paginate(50);

        return view('agent.redeem-codes', compact('codes'));
    }

    public function showGenerate()
    {
        $user = auth()->user();
        $plans = \App\Models\AgentPlan::where('agent_id', $user->id)->active()->ordered()->get();
        return view('agent.redeem-codes-generate', compact('user', 'plans'));
    }

    public function generate(Request $request)
    {
        $data = $request->validate([
            'count' => 'required|integer|min:1|max:500',
            'type' => 'required|in:credits,balance,mixed',
            'credits' => 'required_if:type,credits,mixed|integer|min:0',
            'balance' => 'required_if:type,balance,mixed|numeric|min:0',
            'expires_days' => 'nullable|integer|min:1',
            'agent_plan_id' => 'nullable|exists:agent_plans,id',
        ]);

        $credits = $data['credits'] ?? 0;
        $balance = $data['balance'] ?? 0;
        $count = $data['count'];
        $totalCredits = $count * $credits;
        $totalBalance = bcmul($count, $balance, 2);

        $error = DB::transaction(function () use ($data, $credits, $balance, $count, $totalCredits, $totalBalance) {
            $agent = \App\Models\User::lockForUpdate()->find(auth()->id());

            if ($agent->credits < $totalCredits) {
                return "积分不足，需要 {$totalCredits}，当前 {$agent->credits}";
            }
            if ($agent->balance < $totalBalance) {
                return "余额不足，需要 ¥{$totalBalance}，当前 ¥{$agent->balance}";
            }

            $agent->decrement('credits', $totalCredits);
            $agent->decrement('balance', $totalBalance);

            $batchId = 'A' . now()->format('ymdHis') . Str::random(4);
            $expiresAt = isset($data['expires_days']) ? now()->addDays($data['expires_days']) : null;

            for ($i = 0; $i < $count; $i++) {
                RedeemCode::create([
                    'code' => strtoupper(Str::random(32)),
                    'type' => $data['type'],
                    'credits' => $credits,
                    'balance' => $balance,
                    'status' => 'unused',
                    'created_by' => $agent->id,
                    'expires_at' => $expiresAt,
                    'batch_id' => $batchId,
                    'agent_plan_id' => $data['agent_plan_id'] ?? null,
                ]);
            }

            $agent->refresh();
            AgentTransaction::create([
                'user_id' => $agent->id,
                'type' => 'generate',
                'credits' => -$totalCredits,
                'balance' => -$totalBalance,
                'credits_after' => $agent->credits,
                'balance_after' => $agent->balance,
                'description' => "生成{$count}个兑换码 (批次:{$batchId})",
            ]);

            return null;
        });

        if ($error) {
            return back()->withErrors(['count' => $error])->withInput();
        }

        return redirect()->route('agent.redeem-codes')->with('success', "成功生成 {$count} 个兑换码");
    }

    public function disable(RedeemCode $redeemCode)
    {
        abort_if($redeemCode->created_by !== auth()->id(), 403);
        abort_if($redeemCode->status !== 'unused', 422, '只能禁用未使用的兑换码');

        $redeemCode->update(['status' => 'disabled']);
        return back()->with('success', '已禁用');
    }

    public function export(Request $request)
    {
        $query = RedeemCode::where('created_by', auth()->id());

        if ($batch = $request->query('batch')) {
            $query->where('batch_id', $batch);
        }

        $codes = $query->where('status', 'unused')->latest()->get();

        $csv = "兑换码,类型,积分,余额,过期时间\n";
        foreach ($codes as $code) {
            $csv .= "{$code->code},{$code->type},{$code->credits},{$code->balance},{$code->expires_at}\n";
        }

        return response($csv)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="redeem-codes-' . now()->format('Ymd') . '.csv"');
    }
}
