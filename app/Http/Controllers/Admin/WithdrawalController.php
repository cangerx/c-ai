<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            $requests = WithdrawalRequest::with('user')->latest()->paginate(20);
        } else {
            $requests = WithdrawalRequest::where('user_id', $user->id)->latest()->paginate(20);
        }

        return view('admin.withdrawals.index', compact('requests'));
    }

    public function create()
    {
        return view('admin.withdrawals.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:alipay,wechat,bank',
            'payment_account' => 'required|string|max:200',
        ]);

        $user = auth()->user();

        if ($data['amount'] > $user->commission_balance) {
            return back()->withErrors(['amount' => '提现金额不能超过可用佣金余额'])->withInput();
        }

        DB::transaction(function () use ($user, $data) {
            User::where('id', $user->id)->lockForUpdate()->first()
                ->decrement('commission_balance', $data['amount']);

            WithdrawalRequest::create([
                'user_id' => $user->id,
                'amount' => $data['amount'],
                'status' => 'pending',
                'payment_method' => $data['payment_method'],
                'payment_account' => $data['payment_account'],
            ]);
        });

        return redirect()->route('admin.withdrawals.index')->with('success', '提现申请已提交');
    }

    public function approve(WithdrawalRequest $withdrawalRequest)
    {
        if ($withdrawalRequest->status !== 'pending') {
            return back()->with('error', '该申请已处理');
        }

        $withdrawalRequest->update([
            'status' => 'paid',
            'processed_at' => now(),
        ]);

        return back()->with('success', '已批准并标记为已打款');
    }

    public function reject(WithdrawalRequest $withdrawalRequest)
    {
        if ($withdrawalRequest->status !== 'pending') {
            return back()->with('error', '该申请已处理');
        }

        DB::transaction(function () use ($withdrawalRequest) {
            $withdrawalRequest->update([
                'status' => 'rejected',
                'processed_at' => now(),
            ]);

            // 退回佣金余额
            User::where('id', $withdrawalRequest->user_id)
                ->increment('commission_balance', $withdrawalRequest->amount);
        });

        return back()->with('success', '已拒绝，佣金已退回');
    }
}
