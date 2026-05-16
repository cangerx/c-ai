<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;

class LoginSettingController extends Controller
{
    public function index()
    {
        return view('admin.login-settings.index');
    }

    public function update(Request $request)
    {
        $request->validate([
            'settings' => 'array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable|string',
            'settings.*.group' => 'required|string',
        ]);

        $enableKeys = ['login_github_enabled', 'login_wechat_enabled'];
        $submitted = collect($request->input('settings', []))->keyBy('key');

        foreach ($enableKeys as $key) {
            if ($submitted->has($key)) {
                SiteSetting::set($key, $submitted[$key]['value'] ?? '0', 'login');
            } else {
                SiteSetting::set($key, '0', 'login');
            }
        }

        foreach ($request->input('settings', []) as $item) {
            if (in_array($item['key'], $enableKeys)) continue;
            SiteSetting::set($item['key'], $item['value'] ?? '', $item['group']);
        }

        return back()->with('success', '已保存');
    }
}
