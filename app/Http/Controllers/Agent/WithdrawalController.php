<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;

class WithdrawalController extends Controller
{
    public function index()
    {
        $withdrawals = WithdrawalRequest::where('user_id', auth()->id())->latest()->paginate(20);
        $user = auth()->user();
        return view('agent.withdrawals', compact('withdrawals', 'user'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:10',
            'payment_method' => 'required|string|max:50',
            'payment_account' => 'required|string|max:200',
        ]);

        $user = auth()->user();
        if ($user->commission_balance < $data['amount']) {
            return back()->withErrors(['amount' => '佣金余额不足']);
        }

        $user->decrement('commission_balance', $data['amount']);

        WithdrawalRequest::create([
            'user_id' => $user->id,
            'amount' => $data['amount'],
            'status' => 'pending',
            'payment_method' => $data['payment_method'],
            'payment_account' => $data['payment_account'],
        ]);

        return back()->with('success', '提现申请已提交，等待审核');
    }
}
