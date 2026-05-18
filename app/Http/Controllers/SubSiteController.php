<?php

namespace App\Http\Controllers;

use App\Models\AgentPlan;
use App\Models\AgentSite;

class SubSiteController extends Controller
{
    public function index()
    {
        $site = app('agent_site') ?? abort(404);
        $site->loadMissing('agent');
        $inviteCode = $site->agent?->ensureInviteCode();

        $html = file_get_contents(public_path('index.html'));
        $inject = '<script>window.__AGENT_SITE__=' . json_encode([
            'name' => $site->site_name,
            'color' => $site->theme_color,
            'logo' => $site->logo_url,
            'seo_title' => $site->seo_title,
            'seo_description' => $site->seo_description,
            'announcement' => $site->announcement,
            'invite_code' => $inviteCode,
            'cost_per_generation' => $site->cost_per_generation,
        ]) . ';</script>';

        $html = str_replace('</head>', $inject . '</head>', $html);
        return response($html);
    }

    public function pricing()
    {
        $site = app('agent_site') ?? abort(404);
        $plans = AgentPlan::where('agent_id', $site->user_id)->active()->ordered()->get();
        return view('pricing', ['plans' => $plans, 'agentSite' => $site]);
    }
}
