<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentPlan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index()
    {
        $plans = AgentPlan::where('agent_id', auth()->id())->ordered()->get();
        return view('agent.plans', compact('plans'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'credits' => 'required|integer|min:0',
            'balance' => 'required|numeric|min:0',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'features' => 'nullable|string',
        ]);

        $data['agent_id'] = auth()->id();
        $data['is_featured'] = $request->boolean('is_featured');
        AgentPlan::create($data);

        return back()->with('success', '套餐创建成功');
    }

    public function update(Request $request, AgentPlan $agentPlan)
    {
        abort_if($agentPlan->agent_id !== auth()->id(), 403);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'price' => 'required|numeric|min:0',
            'credits' => 'required|integer|min:0',
            'balance' => 'required|numeric|min:0',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
            'features' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data['is_featured'] = $request->boolean('is_featured');
        $data['is_active'] = $request->boolean('is_active');
        $agentPlan->update($data);

        return back()->with('success', '套餐已更新');
    }

    public function destroy(AgentPlan $agentPlan)
    {
        abort_if($agentPlan->agent_id !== auth()->id(), 403);
        $agentPlan->delete();
        return back()->with('success', '套餐已删除');
    }
}
