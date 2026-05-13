<?php

namespace App\Apps\ImageGen\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessGenerationTask;
use App\Models\GenerationTask;
use App\Models\UsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    /**
     * 任务列表（带筛选 + 分页 + 看板统计）。
     */
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();  // '', pending, processing, completed, failed
        $q      = $request->string('q')->toString();       // task_id/email/nickname 子串
        $range  = $request->string('range', '7d')->toString(); // today, 7d, 30d, all

        // ── 看板统计 ──
        $since = match ($range) {
            'today' => Carbon::today(),
            '30d'   => Carbon::now()->subDays(30),
            'all'   => Carbon::createFromTimestamp(0),
            default => Carbon::now()->subDays(7),
        };

        $statsBase = GenerationTask::where('created_at', '>=', $since);
        $stats = [
            'total'      => (clone $statsBase)->count(),
            'completed'  => (clone $statsBase)->where('status', 'completed')->count(),
            'failed'     => (clone $statsBase)->where('status', 'failed')->count(),
            'processing' => (clone $statsBase)->whereIn('status', ['pending', 'processing'])->count(),
        ];
        $stats['success_rate'] = $stats['total'] > 0
            ? round($stats['completed'] * 100 / max($stats['total'], 1), 1)
            : 0.0;

        // 退款总额（以 usage_logs 的 refunded_at 为准）
        $refundAgg = UsageLog::where('app_name', 'image-gen')
            ->whereNotNull('refunded_at')
            ->where('refunded_at', '>=', $since)
            ->selectRaw('SUM(cost_credits) as credits, SUM(cost_balance) as balance, COUNT(*) as cnt')
            ->first();
        $stats['refund_count']   = (int) ($refundAgg->cnt ?? 0);
        $stats['refund_credits'] = (int) ($refundAgg->credits ?? 0);
        $stats['refund_balance'] = (float) ($refundAgg->balance ?? 0);

        // ── 列表查询 ──
        $query = GenerationTask::with('user:id,email,nickname,name')
            ->orderByDesc('created_at');

        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(function ($w) use ($like) {
                $w->where('task_id', 'like', $like)
                  ->orWhereHas('user', function ($u) use ($like) {
                      $u->where('email', 'like', $like)
                        ->orWhere('nickname', 'like', $like)
                        ->orWhere('name', 'like', $like);
                  });
            });
        }

        $tasks = $query->paginate(30)->withQueryString();

        // 用 task_id 一次性查所有 usage_log，拼装退款信息
        $taskIds = $tasks->pluck('task_id')->all();
        $logsByTask = UsageLog::whereIn('task_id', $taskIds)
            ->where('app_name', 'image-gen')
            ->get()
            ->keyBy('task_id');

        return view('image-gen::admin.tasks', [
            'tasks'      => $tasks,
            'logsByTask' => $logsByTask,
            'stats'      => $stats,
            'filters'    => ['status' => $status, 'q' => $q, 'range' => $range],
        ]);
    }

    /**
     * 单任务详情（含完整错误、payload、items）。
     */
    public function show(string $taskId)
    {
        $task = GenerationTask::with('user')->findOrFail($taskId);
        $usageLog = UsageLog::where('task_id', $taskId)->first();

        return view('image-gen::admin.task-detail', compact('task', 'usageLog'));
    }

    /**
     * 管理员手动重跑任务（重置状态 + 重新派发 Job）。
     * 不退款、不扣款——重用已有 usage_log。
     */
    public function retry(string $taskId, Request $request)
    {
        $task = GenerationTask::findOrFail($taskId);

        if (!in_array($task->status, ['failed', 'completed'], true)) {
            return back()->with('error', '只有已完成或失败的任务可重试，当前状态：' . $task->status);
        }

        $apiKey = $request->input('api_key', '');
        if ($apiKey === '') {
            return back()->with('error', '请提供 API Key 以供 Job 重新请求上游。');
        }

        $task->update([
            'status'   => 'pending',
            'message'  => '管理员手动重跑，已入队。',
            'error'    => null,
            'attempts' => 0,
        ]);

        ProcessGenerationTask::dispatch($task->task_id, $apiKey);

        return back()->with('success', '任务已重新入队：' . $task->task_id);
    }

    /**
     * 管理员手动退款（幂等）。
     */
    public function refund(string $taskId)
    {
        $task = GenerationTask::findOrFail($taskId);
        $log  = UsageLog::where('task_id', $taskId)->first();

        if (!$log) {
            return back()->with('error', '该任务没有计费记录，无法退款。');
        }
        if (!is_null($log->refunded_at)) {
            return back()->with('error', '该任务已退款于 ' . $log->refunded_at);
        }
        if (!$task->user) {
            return back()->with('error', '用户不存在，无法退款。');
        }

        DB::transaction(function () use ($task, $log) {
            if ($log->cost_credits > 0) {
                $task->user->increment('credits', $log->cost_credits);
            }
            if ($log->cost_balance > 0) {
                $task->user->increment('balance', $log->cost_balance);
            }
            $log->update(['refunded_at' => now()]);
        });

        return back()->with('success', sprintf(
            '已退款：credits +%d，balance +%.2f',
            (int) $log->cost_credits,
            (float) $log->cost_balance
        ));
    }

    /**
     * 管理员强制标记任务失败（不影响 worker 中的 Job，仅修改数据库状态；
     * 常用于 worker 已挂但状态卡在 processing 的任务）。
     */
    public function forceFail(string $taskId)
    {
        $task = GenerationTask::findOrFail($taskId);
        if ($task->status === 'completed') {
            return back()->with('error', '任务已完成，不能强制失败。');
        }
        $task->update([
            'status'  => 'failed',
            'message' => '管理员强制标记失败。',
            'error'   => ($task->error ?: '') . ' | 管理员手动强制失败于 ' . now(),
        ]);
        return back()->with('success', '任务已强制标记为失败：' . $taskId);
    }
}
