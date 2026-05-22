<?php

namespace Database\Seeders;

use App\Models\PaymentPackage;
use Illuminate\Database\Seeder;

class PaymentPackagesSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            ['code' => 'pkg_5',   'name' => '体验包',    'amount' => 5,   'credits' => 500,   'bonus_credits' => 0,    'sort' => 1],
            ['code' => 'pkg_10',  'name' => '入门包',    'amount' => 10,  'credits' => 1000,  'bonus_credits' => 50,   'sort' => 2],
            ['code' => 'pkg_30',  'name' => '标准包',    'amount' => 30,  'credits' => 3000,  'bonus_credits' => 300,  'sort' => 3],
            ['code' => 'pkg_50',  'name' => '热销包',    'amount' => 50,  'credits' => 5000,  'bonus_credits' => 800,  'sort' => 4],
            ['code' => 'pkg_100', 'name' => '高性价比',  'amount' => 100, 'credits' => 10000, 'bonus_credits' => 2000, 'sort' => 5],
        ];

        foreach ($packages as $p) {
            PaymentPackage::updateOrCreate(['code' => $p['code']], $p + ['is_active' => true]);
        }
    }
}
