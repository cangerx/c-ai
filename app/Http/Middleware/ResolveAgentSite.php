<?php

namespace App\Http\Middleware;

use App\Models\AgentSite;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ResolveAgentSite
{
    public function handle(Request $request, Closure $next)
    {
        $path = $request->path();
        if (str_starts_with($path, 'admin') || str_starts_with($path, 'agent') || str_starts_with($path, 'api/') || str_starts_with($path, 'install')) {
            return $next($request);
        }

        $host = $request->getHost();
        $mainDomain = config('app.domain');
        $site = null;

        if ($mainDomain && $host !== $mainDomain && $host !== 'www.' . $mainDomain) {
            if (str_ends_with($host, '.' . $mainDomain)) {
                $sub = str_replace('.' . $mainDomain, '', $host);
                $site = Cache::remember("agent_site:sub:{$sub}", 300, fn() =>
                    AgentSite::where('subdomain', $sub)->where('is_active', true)->first()
                );
            } else {
                $site = Cache::remember("agent_site:domain:{$host}", 300, fn() =>
                    AgentSite::where('custom_domain', $host)->where('is_active', true)->first()
                );
            }
        }

        if ($site) {
            app()->instance('agent_site', $site);
            $request->attributes->set('agent_site', $site);
        }

        return $next($request);
    }
}
