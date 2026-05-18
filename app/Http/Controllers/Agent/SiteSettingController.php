<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentSite;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class SiteSettingController extends Controller
{
    public function edit()
    {
        $site = AgentSite::firstOrNew(['user_id' => auth()->id()]);
        return view('agent.site-settings', compact('site'));
    }

    public function update(Request $request)
    {
        $userId = auth()->id();
        $site = AgentSite::firstOrNew(['user_id' => $userId]);

        $data = $request->validate([
            'slug' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('agent_sites')->ignore($site->id), 'not_in:admin,agent,api,s,install,login,register,explore,pricing,terms,privacy'],
            'subdomain' => ['nullable', 'string', 'max:32', 'alpha_dash', Rule::unique('agent_sites')->ignore($site->id)],
            'custom_domain' => ['nullable', 'string', 'max:255', Rule::unique('agent_sites')->ignore($site->id)],
            'site_name' => 'required|string|max:100',
            'logo_url' => 'nullable|string|max:500',
            'theme_color' => 'nullable|string|max:7',
            'seo_title' => 'nullable|string|max:200',
            'seo_description' => 'nullable|string|max:500',
            'seo_keywords' => 'nullable|string|max:300',
            'announcement' => 'nullable|string|max:1000',
            'cost_per_generation' => 'nullable|integer|min:1',
            'commission_rate' => 'nullable|integer|min:0|max:100',
        ]);

        $data['user_id'] = $userId;
        $data['theme_color'] = $data['theme_color'] ?: '#2d5bf0';

        if ($site->exists) {
            $site->update($data);
            // Clear cache
            Cache::forget("agent_site:domain:{$site->custom_domain}");
            Cache::forget("agent_site:sub:{$site->subdomain}");
        } else {
            $data['status'] = 'pending';
            $data['is_active'] = false;
            AgentSite::create($data);
            return back()->with('success', '分站申请已提交，等待管理员审核');
        }

        return back()->with('success', '分站设置已保存');
    }
}
