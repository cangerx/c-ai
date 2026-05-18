<?php

namespace App\Apps\ImageGen\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiChannel;
use App\Models\GenerationTask;
use App\Models\UsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class ChannelController extends Controller
{
    public function index()
    {
        $channels = AiChannel::where('app_name', 'image-gen')->orderByDesc('priority')->get();

        // 每个渠道的近24h统计
        $since = Carbon::now()->subHours(24);
        $channelStats = [];
        foreach ($channels as $ch) {
            $taskIds = UsageLog::where('channel_id', $ch->id)
                ->where('created_at', '>=', $since)
                ->whereNotNull('task_id')
                ->pluck('task_id');

            $tasks = GenerationTask::whereIn('task_id', $taskIds)
                ->selectRaw("COUNT(*) as total, SUM(status='completed') as ok, SUM(status='failed') as fail")
                ->first();

            $hasCooldown = Cache::has("channel_cooldown:{$ch->id}");

            $channelStats[$ch->id] = [
                'total' => (int) ($tasks->total ?? 0),
                'ok' => (int) ($tasks->ok ?? 0),
                'fail' => (int) ($tasks->fail ?? 0),
                'cooldown' => $hasCooldown,
            ];
        }

        return view('image-gen::admin.channels', compact('channels', 'channelStats'));
    }

    public function create()
    {
        return view('image-gen::admin.channel-form', ['channel' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'display_name' => 'nullable|string|max:100',
            'provider' => 'required|string|max:50',
            'base_url' => 'required|url|max:500',
            'api_key' => 'required|string|max:500',
            'model' => 'nullable|string|max:100',
            'priority' => 'integer|min:0|max:100',
            'request_mode' => 'required|in:sync,async,stream',
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
            'display_name' => 'nullable|string|max:100',
            'provider' => 'required|string|max:50',
            'base_url' => 'required|url|max:500',
            'api_key' => 'required|string|max:500',
            'model' => 'nullable|string|max:100',
            'priority' => 'integer|min:0|max:100',
            'request_mode' => 'required|in:sync,async,stream',
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

    /**
     * AJAX: 测试渠道连通性
     */
    public function test(AiChannel $channel)
    {
        $start = microtime(true);
        try {
            $endpoint = rtrim($channel->base_url, '/') . '/v1/images/generations';
            if ($channel->request_mode === 'async') {
                $endpoint .= '?async=true';
            }

            $response = Http::timeout(15)
                ->connectTimeout(5)
                ->withHeaders([
                    'Authorization' => "Bearer {$channel->api_key}",
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'model' => 'gpt-image-2',
                    'prompt' => 'a single white pixel',
                    'size' => '1024x1024',
                    'quality' => 'low',
                    'n' => 1,
                ]);

            $latency = round((microtime(true) - $start) * 1000);

            if ($response->successful()) {
                $msg = "成功 ({$latency}ms)";
                if ($channel->request_mode === 'async') {
                    $id = $response->json('id');
                    $msg = "异步任务已提交: {$id} ({$latency}ms)";
                }
                return response()->json(['status' => 'ok', 'latency' => $latency, 'msg' => $msg]);
            }

            return response()->json(['status' => 'error', 'latency' => $latency, 'msg' => "HTTP {$response->status()}: " . mb_substr($response->body(), 0, 100)]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $latency = round((microtime(true) - $start) * 1000);
            if (str_contains($e->getMessage(), 'timed out') || str_contains($e->getMessage(), 'cURL error 28')) {
                return response()->json(['status' => 'ok', 'latency' => $latency, 'msg' => "连通 (生成中, {$latency}ms)"]);
            }
            return response()->json(['status' => 'error', 'latency' => $latency, 'msg' => $e->getMessage()]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'latency' => 0, 'msg' => $e->getMessage()]);
        }
    }
}
