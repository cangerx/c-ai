<?php

namespace App\Http\Middleware;

use App\Models\AgentSite;
use App\Models\SiteSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

        try {
            if ($mainDomain && $host !== $mainDomain && $host !== 'www.' . $mainDomain) {
                $columns = Cache::remember('agent_sites_columns', 300, fn () => Schema::hasTable('agent_sites') ? Schema::getColumnListing('agent_sites') : []);
                $hasColumn = fn (string $column) => in_array($column, $columns, true);

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
                        $site = Cache::remember("agent_site:sub:{$sub}@{$domain}", 300, function () use ($sub, $domain, $isMainDomain, $hasColumn) {
                            $query = AgentSite::where('subdomain', $sub);

                            if ($hasColumn('subdomain_domain')) {
                                $query->where(function ($q) use ($domain, $isMainDomain) {
                                    $q->where('subdomain_domain', $domain);
                                    if ($isMainDomain) {
                                        $q->orWhereNull('subdomain_domain');
                                    }
                                });
                            }

                            if ($hasColumn('is_active')) {
                                $query->where('is_active', true);
                            }

                            return $query->first();
                        });
                        $matched = true;
                        break;
                    }
                }

                if (!$matched && $hasColumn('custom_domain')) {
                    $site = Cache::remember("agent_site:domain:{$host}", 300, function () use ($host, $hasColumn) {
                        $query = AgentSite::where('custom_domain', $host);

                        if ($hasColumn('is_active')) {
                            $query->where('is_active', true);
                        }

                        return $query->first();
                    });
                }
            }
        } catch (\Throwable $e) {
            Log::error('ResolveAgentSite failed', [
                'host' => $host,
                'path' => $path,
                'error' => $e->getMessage(),
                'file' => $e->getFile() . ':' . $e->getLine(),
            ]);
            Cache::forget('agent_sites_columns');
            Cache::forget('wildcard_domains_list');
        }

        if ($site) {
            app()->instance('agent_site', $site);
            $request->attributes->set('agent_site', $site);
        }

        return $next($request);
    }
}
