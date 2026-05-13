<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RedeemCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RedeemController extends Controller
{
    public function redeem(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|size:32',
        ]);

        $user = $request->user();

        return DB::transaction(function () use ($data, $user) {
            $code = RedeemCode::where('code', $data['code'])
                ->where('status', 'unused')
                ->lockForUpdate()
                ->first();

            if (!$code) {
                return response()->json(['message' => '兑换码无效或已使用'], 422);
            }

            if ($code->isExpired()) {
                return response()->json(['message' => '兑换码已过期'], 422);
            }

            $code->update([
                'status' => 'used',
                'used_by' => $user->id,
                'used_at' => now(),
            ]);

            $user->increment('credits', $code->credits);
            $user->increment('balance', $code->balance);
            $user->refresh();

            return response()->json([
                'message' => '兑换成功',
                'added_credits' => $code->credits,
                'added_balance' => $code->balance,
                'user' => [
                    'credits' => $user->credits,
                    'balance' => $user->balance,
                ],
            ]);
        });
    }
}
