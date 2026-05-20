<?php

namespace App\Http\Middleware;

use App\Models\AgentSite;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ResolveAgentSite
{
    public function handle(Request $request, Closure $next)
    {
        $path = $request->path();
        if (str_starts_with($path, 'admin') || str_starts_with($path, 'agent') || str_starts_with($path, 'api/') || str_starts_with($path, 'install')) {
            return $next($request);
        }

        $host = $request->getHost();
        $site = null;

        try {
            $site = AgentSite::resolveForHost($host);
        } catch (\Throwable $e) {
            Log::error('ResolveAgentSite failed', [
                'host' => $host,
                'path' => $path,
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
        }

        if ($site instanceof AgentSite) {
            app()->instance('agent_site', $site);
            $request->attributes->set('agent_site', $site);
        }

        return $next($request);
    }
}
