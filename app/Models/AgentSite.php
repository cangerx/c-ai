<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AgentSite extends Model
{
    protected $fillable = [
        'user_id', 'slug', 'subdomain', 'custom_domain', 'site_name', 'logo_url',
        'theme_color', 'seo_title', 'seo_description', 'seo_keywords',
        'announcement', 'cost_per_generation', 'commission_rate', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function agent()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
