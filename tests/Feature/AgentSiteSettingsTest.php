<?php

namespace Tests\Feature;

use App\Filament\Agent\Pages\SiteSettings;
use App\Models\AgentSite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgentSiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_homepage_settings_persist_after_save_and_refresh(): void
    {
        $agent = User::factory()->agent()->create(['invite_code' => 'AGENT001']);

        AgentSite::create([
            'user_id' => $agent->id,
            'slug' => 'agent001',
            'subdomain' => 'agent001',
            'site_name' => '旧站点',
            'theme_color' => '#2d5bf0',
            'status' => 'approved',
            'is_active' => true,
        ]);

        $this->actingAs($agent);

        Livewire::test(SiteSettings::class)
            ->set('data.site_name', '新站点')
            ->set('data.subdomain', 'agent001')
            ->set('data.theme_color', '#123456')
            ->set('data.hero_title', '首页大标题')
            ->set('data.hero_subtitle', '首页副标题')
            ->set('data.hero_bg_url', 'https://example.com/bg.png')
            ->set('data.hero_bg_color', '#abcdef')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('agent_sites', [
            'user_id' => $agent->id,
            'site_name' => '新站点',
            'hero_title' => '首页大标题',
            'hero_subtitle' => '首页副标题',
            'hero_bg_url' => 'https://example.com/bg.png',
            'hero_bg_color' => '#abcdef',
        ]);

        Livewire::test(SiteSettings::class)
            ->assertSet('data.hero_title', '首页大标题')
            ->assertSet('data.hero_subtitle', '首页副标题')
            ->assertSet('data.hero_bg_url', 'https://example.com/bg.png')
            ->assertSet('data.hero_bg_color', '#abcdef');
    }
}
