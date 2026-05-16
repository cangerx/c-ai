<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::ordered()->get();
        return view('admin.plans.index', compact('plans'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:once,subscription',
            'price' => 'required|numeric|min:0',
            'credits' => 'integer|min:0',
            'balance' => 'numeric|min:0',
            'duration_days' => 'nullable|integer|min:1',
            'is_featured' => 'boolean',
            'sort_order' => 'integer|min:0',
            'features' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active', true);

        Plan::create($data);
        return back()->with('success', '套餐已创建');
    }

    public function update(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'type' => 'required|in:once,subscription',
            'price' => 'required|numeric|min:0',
            'credits' => 'integer|min:0',
            'balance' => 'numeric|min:0',
            'duration_days' => 'nullable|integer|min:1',
            'is_featured' => 'boolean',
            'sort_order' => 'integer|min:0',
            'features' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');

        $plan->update($data);
        return back()->with('success', '套餐已更新');
    }

    public function destroy(Plan $plan)
    {
        $plan->delete();
        return back()->with('success', '套餐已删除');
    }
}
