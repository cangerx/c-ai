<?php

namespace App\Apps\ImageGen\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GenerationTask;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = 12;

        $query = GenerationTask::where('is_public', true)
            ->where('status', 'completed')
            ->with('user:id,name,nickname');

        if ($search = $request->input('q')) {
            $query->where('prompt', 'like', "%{$search}%");
        }

        if ($model = $request->input('model')) {
            $query->where('model', $model);
        }

        $tasks = $query->latest()->paginate($perPage);

        $items = $tasks->getCollection()->filter(function ($task) {
            return !empty($task->items);
        })->map(function ($task) {
            $images = collect($task->items)->filter(fn($i) => !empty($i['url']));
            if ($images->isEmpty()) return null;

            $user = $task->user;

            return [
                'task_id' => $task->task_id,
                'prompt' => $task->prompt,
                'model' => $task->model,
                'size' => $task->size,
                'quality' => $task->quality,
                'image_count' => $images->count(),
                'thumb' => $images->first()['url'] ?? null,
                'images' => $images->pluck('url')->values()->all(),
                'created_at' => $task->created_at?->toIso8601String(),
                'time_ago' => $task->created_at?->diffForHumans(),
                'author' => [
                    'name' => $user?->nickname ?: $user?->name ?: '匿名',
                    'avatar' => null,
                ],
            ];
        })->filter()->values();

        if ($request->wantsJson() || $request->is('api/*')) {
            return response()->json([
                'ok' => true,
                'items' => $items,
                'page' => $tasks->currentPage(),
                'total_pages' => $tasks->lastPage(),
                'total' => $tasks->total(),
            ]);
        }

        return view('explore', [
            'items' => $items,
            'page' => $tasks->currentPage(),
            'totalPages' => $tasks->lastPage(),
            'total' => $tasks->total(),
        ]);
    }
}
