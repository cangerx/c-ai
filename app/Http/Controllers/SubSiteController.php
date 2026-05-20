<?php

namespace App\Http\Controllers;

use App\Models\AgentPlan;
use App\Models\AgentSite;

class SubSiteController extends Controller
{
    public function index()
    {
        $site = app('agent_site') ?? abort(404);

        try {
            $site->loadMissing('agent');

            $inviteCode = null;
            try {
                $inviteCode = $site->agent?->ensureInviteCode();
            } catch (\Throwable $e) {
            }

            $html = file_get_contents(public_path('index.html'));
            if (!$html) {
                \Log::error('SubSite: index.html not found or empty', ['path' => public_path('index.html')]);
                abort(500, '站点文件缺失');
            }

            $attrs = $site->getAttributes();
            $payload = [
                'name' => $attrs['site_name'] ?? null,
                'color' => $attrs['theme_color'] ?? null,
                'logo' => $attrs['logo_url'] ?? null,
                'seo_title' => $attrs['seo_title'] ?? null,
                'seo_description' => $attrs['seo_description'] ?? null,
                'seo_keywords' => $attrs['seo_keywords'] ?? null,
                'hero_title' => $attrs['hero_title'] ?? null,
                'hero_subtitle' => $attrs['hero_subtitle'] ?? null,
                'hero_bg_url' => $attrs['hero_bg_url'] ?? null,
                'hero_bg_color' => $attrs['hero_bg_color'] ?? null,
                'footer_text' => $attrs['footer_text'] ?? null,
                'footer_icp' => $attrs['footer_icp'] ?? null,
                'footer_links' => $site->footer_links,
                'announcement' => $attrs['announcement'] ?? null,
                'invite_code' => $inviteCode,
                'cost_per_generation' => $attrs['cost_per_generation'] ?? null,
            ];

            $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
            if ($json === false) {
                \Log::error('SubSite: json_encode failed', ['error' => json_last_error_msg(), 'site_id' => $site->id]);
                $json = '{}';
            }

            $inject = '<script>window.__AGENT_SITE__=' . $json . ';</script>';
            $html = str_replace('</head>', $inject . '</head>', $html);

            return response($html);
        } catch (\Throwable $e) {
            \Log::error('SubSite index error', [
                'site_id' => $site->id ?? null,
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            // 降级：返回原始 index.html
            $fallback = @file_get_contents(public_path('index.html'));
            if ($fallback) {
                return response($fallback);
            }
            abort(500, '站点加载失败');
        }
    }

    public function pricing()
    {
        $site = app('agent_site') ?? abort(404);
        $plans = AgentPlan::where('agent_id', $site->user_id)->active()->ordered()->get();
        return view('pricing', ['plans' => $plans, 'agentSite' => $site]);
    }
}
