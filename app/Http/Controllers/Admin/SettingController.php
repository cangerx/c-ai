<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SettingController extends Controller
{
    public function index()
    {
        $settings = SiteSetting::orderBy('group')->orderBy('key')->get()->groupBy('group');
        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*.key' => 'required|string',
            'settings.*.value' => 'nullable|string',
            'settings.*.group' => 'required|string',
        ]);

        foreach ($data['settings'] as $item) {
            SiteSetting::set($item['key'], $item['value'] ?? '', $item['group']);
        }

        return redirect()->route('admin.settings.index', ['tab' => $request->input('tab', 'seo')])->with('success', '设置已保存');
    }

    public function testMail()
    {
        try {
            $to = auth()->user()->email;
            Mail::raw('这是一封来自 CANG-AI 的测试邮件，收到说明邮件配置正确。', function ($msg) use ($to) {
                $msg->to($to)->subject('CANG-AI 邮件测试');
            });
            return response()->json(['message' => "测试邮件已发送至 {$to}"]);
        } catch (\Throwable $e) {
            return response()->json(['message' => '发送失败：' . $e->getMessage()], 422);
        }
    }
}
