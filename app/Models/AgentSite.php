<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class AgentSite extends Model
{
    protected $fillable = [
        'user_id', 'slug', 'subdomain', 'subdomain_domain', 'custom_domain', 'site_name', 'logo_url',
        'theme_color', 'seo_title', 'seo_description', 'seo_keywords',
        'footer_text', 'footer_icp', 'footer_links',
        'hero_title', 'hero_subtitle', 'hero_bg_url', 'hero_bg_color',
        'announcement', 'cost_per_generation', 'commission_rate', 'is_active',
        'status', 'reject_reason', 'approved_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $site) {
            if (empty($site->slug)) {
                $site->slug = $site->subdomain ?: str()->random(8);
            }
        });

        static::saved(function (self $site) {
            $site->clearResolutionCache();
        });

        static::deleted(function (self $site) {
            $site->clearResolutionCache();
        });
    }

    public function clearResolutionCache(): void
    {
        Cache::forget("agent_site:sub:{$this->subdomain}@{$this->subdomain_domain}");
        Cache::forget("agent_site:sub:{$this->subdomain}@");
        Cache::forget("agent_site:sub:{$this->subdomain}@" . config('app.domain'));
        if (!empty($this->custom_domain)) {
            Cache::forget("agent_site:domain:{$this->custom_domain}");
        }
        Cache::forget('agent_sites_columns');
        Cache::forget('wildcard_domains_list');
    }

    public static function resolveForHost(string $host): ?self
    {
        $mainDomain = config('app.domain');
        if (!$mainDomain || $host === $mainDomain || $host === 'www.' . $mainDomain) {
            return null;
        }

        try {
            $columns = Cache::remember('agent_sites_columns', 86400, fn () => Schema::hasTable('agent_sites') ? Schema::getColumnListing('agent_sites') : []);
            $hasColumn = fn (string $column) => in_array($column, $columns, true);

            $wildcardDomains = Cache::remember('wildcard_domains_list', 86400, function () use ($mainDomain) {
                $domains = json_decode(SiteSetting::get('wildcard_domains', '[]'), true) ?: [];
                if (!in_array($mainDomain, $domains)) {
                    $domains[] = $mainDomain;
                }
                usort($domains, fn($a, $b) => strlen($b) - strlen($a));
                return $domains;
            });

            foreach ($wildcardDomains as $domain) {
                if (str_ends_with($host, '.' . $domain)) {
                    $sub = substr($host, 0, -(strlen($domain) + 1));
                    $isMainDomain = ($domain === $mainDomain);
                    $site = Cache::remember("agent_site:sub:{$sub}@{$domain}", 86400, function () use ($sub, $domain, $isMainDomain, $hasColumn) {
                        $query = self::where('subdomain', $sub);

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

                    return $site;
                }
            }

            if ($hasColumn('custom_domain')) {
                $site = Cache::remember("agent_site:domain:{$host}", 86400, function () use ($host, $hasColumn) {
                    $query = self::where('custom_domain', $host);

                    if ($hasColumn('is_active')) {
                        $query->where('is_active', true);
                    }

                    return $query->first();
                });

                return $site;
            }
        } catch (\Throwable $e) {
            Cache::forget('agent_sites_columns');
            Cache::forget('wildcard_domains_list');
        }

        return null;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
            'footer_links' => 'array',
        ];
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
