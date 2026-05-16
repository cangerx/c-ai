<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentTransaction;
use App\Models\RedeemCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RechargeController extends Controller
{
    public function redeem(Request $request)
    {
        $request->validate(['code' => 'required|string|size:32']);

        $user = auth()->user();
        $code = $request->input('code');

        $error = DB::transaction(function () use ($code, $user) {
            $redeemCode = RedeemCode::where('code', $code)
                ->where('status', 'unused')
                ->lockForUpdate()
                ->first();

            if (!$redeemCode) {
                return '兑换码无效或已使用';
            }
            if ($redeemCode->isExpired()) {
                return '兑换码已过期';
            }

            $redeemCode->update([
                'status' => 'used',
                'used_by' => $user->id,
                'used_at' => now(),
            ]);

            $user->increment('credits', $redeemCode->credits);
            $user->increment('balance', $redeemCode->balance);
            $user->increment('total_recharged', $redeemCode->credits * 0.1 + $redeemCode->balance);

            $user->refresh();

            AgentTransaction::create([
                'user_id' => $user->id,
                'type' => 'recharge',
                'credits' => $redeemCode->credits,
                'balance' => $redeemCode->balance,
                'credits_after' => $user->credits,
                'balance_after' => $user->balance,
                'description' => "兑换码充值 +{$redeemCode->credits}积分 +¥{$redeemCode->balance}",
            ]);

            $this->checkLevelUpgrade($user);
            return null;
        });

        if ($error) {
            return back()->withErrors(['code' => $error]);
        }

        return back()->with('success', '充值成功！');
    }

    private function checkLevelUpgrade($user): void
    {
        $nextLevel = \App\Models\AgentLevel::where('min_recharge', '<=', $user->total_recharged)
            ->orderBy('min_recharge', 'desc')
            ->first();

        if ($nextLevel && $user->agent_level_id !== $nextLevel->id) {
            $user->update(['agent_level_id' => $nextLevel->id]);
        }
    }
}
