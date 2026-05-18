<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GenerationTask;
use App\Models\UsageLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function updateMe(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'nickname' => 'nullable|string|max:50',
            'password' => 'nullable|string|min:6',
            'current_password' => 'required_with:password|string',
        ]);

        if (!empty($data['password'])) {
            if (!Hash::check($data['current_password'], $user->password)) {
                return response()->json(['message' => '当前密码错误'], 422);
            }
            $user->password = Hash::make($data['password']);
            $user->save();
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        }

        if (array_key_exists('nickname', $data)) {
            $user->nickname = $data['nickname'];
        }

        $user->save();

        return response()->json(['message' => '更新成功', 'user' => $user->only('id', 'name', 'email', 'nickname', 'role')]);
    }

    public function usage(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 20), 50);

        $logs = UsageLog::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        return response()->json($logs);
    }

    public function tasks(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 20), 50);

        $tasks = GenerationTask::where('user_id', $request->user()->id)
            ->latest()
            ->paginate($perPage);

        return response()->json($tasks);
    }

    public function deleteTask(Request $request, string $taskId)
    {
        $task = GenerationTask::where('task_id', $taskId)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$task) {
            return response()->json(['message' => '任务不存在'], 404);
        }

        $task->delete();

        return response()->json(['message' => '已删除']);
    }
}
