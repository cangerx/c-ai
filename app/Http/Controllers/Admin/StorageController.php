<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Services\ImageStorageService;
use Illuminate\Http\Request;

class StorageController extends Controller
{
    public function index()
    {
        return view('admin.storage.index');
    }

    public function update(Request $request)
    {
        $fields = ['storage_driver', 'storage_access_key', 'storage_secret_key', 'storage_bucket', 'storage_endpoint', 'storage_region', 'storage_url'];

        foreach ($fields as $key) {
            $value = $request->input($key, '');
            // 密码字段为空时不覆盖
            if ($key === 'storage_secret_key' && $value === '') {
                continue;
            }
            SiteSetting::set($key, $value, 'storage');
        }

        return back()->with('success', '云存储配置已保存');
    }

    public function test()
    {
        try {
            $service = app(ImageStorageService::class);
            $testKey = 'test/' . bin2hex(random_bytes(4)) . '.txt';
            $disk = $this->getActiveDisk();
            $disk->put($testKey, 'connection-test');
            $disk->delete($testKey);

            return back()->with('success', '连接测试成功，存储服务可用');
        } catch (\Throwable $e) {
            return back()->with('error', '连接测试失败：' . $e->getMessage());
        }
    }

    private function getActiveDisk()
    {
        $driver = SiteSetting::get('storage_driver', 'local');
        if (in_array($driver, ['oss', 'r2'])) {
            $config = [
                'driver' => 's3',
                'key' => SiteSetting::get('storage_access_key', ''),
                'secret' => SiteSetting::get('storage_secret_key', ''),
                'region' => SiteSetting::get('storage_region', 'auto') ?: 'auto',
                'bucket' => SiteSetting::get('storage_bucket', ''),
                'endpoint' => SiteSetting::get('storage_endpoint', ''),
                'url' => SiteSetting::get('storage_url', ''),
                'use_path_style_endpoint' => $driver === 'oss',
                'throw' => true,
            ];
            config(['filesystems.disks.dynamic_s3' => $config]);
            return \Illuminate\Support\Facades\Storage::disk('dynamic_s3');
        }
        return \Illuminate\Support\Facades\Storage::disk(config('services.image_storage.disk', 'public'));
    }
}
