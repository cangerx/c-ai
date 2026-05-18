<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentSite extends Model
{
    protected $fillable = [
        'user_id', 'slug', 'subdomain', 'subdomain_domain', 'custom_domain', 'site_name', 'logo_url',
        'theme_color', 'seo_title', 'seo_description', 'seo_keywords',
        'announcement', 'cost_per_generation', 'commission_rate', 'is_active',
        'status', 'reject_reason', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'approved_at' => 'datetime',
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
