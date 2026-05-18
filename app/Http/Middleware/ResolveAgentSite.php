<?php

namespace App\Http\Middleware;

use App\Models\AgentSite;
use App\Models\SiteSetting;
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
            $wildcardDomains = Cache::remember('wildcard_domains_list', 300, function () use ($mainDomain) {
                $domains = json_decode(SiteSetting::get('wildcard_domains', '[]'), true) ?: [];
                if (!in_array($mainDomain, $domains)) {
                    $domains[] = $mainDomain;
                }
                usort($domains, fn($a, $b) => strlen($b) - strlen($a));
                return $domains;
            });

            $matched = false;
            foreach ($wildcardDomains as $domain) {
                if (str_ends_with($host, '.' . $domain)) {
                    $sub = substr($host, 0, -(strlen($domain) + 1));
                    $isMainDomain = ($domain === $mainDomain);
                    $site = Cache::remember("agent_site:sub:{$sub}@{$domain}", 300, function () use ($sub, $domain, $isMainDomain) {
                        return AgentSite::where('subdomain', $sub)
                            ->where(function ($q) use ($domain, $isMainDomain) {
                                $q->where('subdomain_domain', $domain);
                                if ($isMainDomain) {
                                    $q->orWhereNull('subdomain_domain');
                                }
                            })
                            ->where('is_active', true)
                            ->first();
                    });
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
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
