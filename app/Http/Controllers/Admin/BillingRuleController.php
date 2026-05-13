<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingRule;
use Illuminate\Http\Request;

class BillingRuleController extends Controller
{
    public function index()
    {
        $rules = BillingRule::orderBy('app_name')->orderBy('model_pattern')->get();
        return view('admin.billing-rules.index', compact('rules'));
    }

    public function create()
    {
        return view('admin.billing-rules.form', ['rule' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'app_name' => 'required|string|max:64',
            'model_pattern' => 'required|string|max:100',
            'quality' => 'nullable|string|in:low,medium,high',
            'cost_credits' => 'required|integer|min:0',
            'cost_balance' => 'required|numeric|min:0',
        ]);

        BillingRule::create($data);
        return redirect()->route('admin.billing-rules.index')->with('success', '计费规则已创建');
    }

    public function edit(BillingRule $billingRule)
    {
        return view('admin.billing-rules.form', ['rule' => $billingRule]);
    }

    public function update(Request $request, BillingRule $billingRule)
    {
        $data = $request->validate([
            'app_name' => 'required|string|max:64',
            'model_pattern' => 'required|string|max:100',
            'quality' => 'nullable|string|in:low,medium,high',
            'cost_credits' => 'required|integer|min:0',
            'cost_balance' => 'required|numeric|min:0',
        ]);

        $billingRule->update($data);
        return redirect()->route('admin.billing-rules.index')->with('success', '计费规则已更新');
    }

    public function destroy(BillingRule $billingRule)
    {
        $billingRule->delete();
        return back()->with('success', '计费规则已删除');
    }
}
