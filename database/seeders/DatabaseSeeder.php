<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin',
            'email' => 'admin@cang-ai.com',
            'password' => bcrypt(env('ADMIN_PASSWORD', 'ChangeMe!2024')),
            'nickname' => '超级管理员',
            'role' => 'admin',
            'status' => 'active',
            'balance' => 0,
            'credits' => 0,
        ]);

        $settings = [
            ['key' => 'site_name', 'value' => 'CANG-AI', 'group' => 'general'],
            ['key' => 'site_description', 'value' => 'AI 图像生成平台', 'group' => 'general'],
            ['key' => 'announcement', 'value' => 'CANG-AI 全新上线，支持 GPT-image 图像生成', 'group' => 'general'],
            ['key' => 'billing_low_credits', 'value' => '1', 'group' => 'billing'],
            ['key' => 'billing_low_balance', 'value' => '0.10', 'group' => 'billing'],
            ['key' => 'billing_medium_credits', 'value' => '2', 'group' => 'billing'],
            ['key' => 'billing_medium_balance', 'value' => '0.30', 'group' => 'billing'],
            ['key' => 'billing_high_credits', 'value' => '4', 'group' => 'billing'],
            ['key' => 'billing_high_balance', 'value' => '1.00', 'group' => 'billing'],
            ['key' => 'register_gift_credits', 'value' => '5', 'group' => 'billing'],
            ['key' => 'register_gift_balance', 'value' => '0', 'group' => 'billing'],
            ['key' => 'agent_commission_rate', 'value' => '0.10', 'group' => 'agent'],
        ];

        foreach ($settings as $s) {
            \App\Models\SiteSetting::create($s);
        }
    }
}
