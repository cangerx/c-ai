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
    }

    public static function resolveForHost(string $host): ?self
    {
        $mainDomain = config('app.domain');
        if (!$mainDomain || $host === $mainDomain || $host === 'www.' . $mainDomain) {
            return null;
        }

        try {
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

            foreach ($wildcardDomains as $domain) {
                if (str_ends_with($host, '.' . $domain)) {
                    $sub = substr($host, 0, -(strlen($domain) + 1));
                    $isMainDomain = ($domain === $mainDomain);
                    $siteId = Cache::remember("agent_site_id:sub:{$sub}@{$domain}", 300, function () use ($sub, $domain, $isMainDomain, $hasColumn) {
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

                        return $query->value('id');
                    });

                    return $siteId ? self::find($siteId) : null;
                }
            }

            if ($hasColumn('custom_domain')) {
                $siteId = Cache::remember("agent_site_id:domain:{$host}", 300, function () use ($host, $hasColumn) {
                    $query = self::where('custom_domain', $host);

                    if ($hasColumn('is_active')) {
                        $query->where('is_active', true);
                    }

                    return $query->value('id');
                });

                return $siteId ? self::find($siteId) : null;
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
