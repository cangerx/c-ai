<?php

namespace App\Apps\ImageGen\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiChannel;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    public function index()
    {
        $channels = AiChannel::where('app_name', 'image-gen')->orderByDesc('priority')->get();
        return view('image-gen::admin.channels', compact('channels'));
    }

    public function create()
    {
        return view('image-gen::admin.channel-form', ['channel' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'provider' => 'required|string|max:50',
            'base_url' => 'required|url|max:500',
            'api_key' => 'required|string|max:500',
            'model' => 'nullable|string|max:100',
            'priority' => 'integer|min:0|max:100',
            'request_mode' => 'required|in:sync,stream',
        ]);

        AiChannel::create([
            ...$data,
            'app_name' => 'image-gen',
            'status' => 'active',
            'config' => [],
        ]);

        return redirect()->route('admin.image-gen.channels')->with('success', '渠道已创建');
    }

    public function edit(AiChannel $channel)
    {
        return view('image-gen::admin.channel-form', compact('channel'));
    }

    public function update(Request $request, AiChannel $channel)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'provider' => 'required|string|max:50',
            'base_url' => 'required|url|max:500',
            'api_key' => 'required|string|max:500',
            'model' => 'nullable|string|max:100',
            'priority' => 'integer|min:0|max:100',
            'request_mode' => 'required|in:sync,stream',
        ]);

        $channel->update($data);

        return redirect()->route('admin.image-gen.channels')->with('success', '渠道已更新');
    }

    public function toggleStatus(AiChannel $channel)
    {
        $channel->update([
            'status' => $channel->status === 'active' ? 'disabled' : 'active',
        ]);

        return back()->with('success', $channel->status === 'active' ? '渠道已启用' : '渠道已禁用');
    }

    public function destroy(AiChannel $channel)
    {
        $channel->delete();

        return redirect()->route('admin.image-gen.channels')->with('success', '渠道已删除');
    }
}
