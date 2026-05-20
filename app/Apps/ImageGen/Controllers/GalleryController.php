<?php

namespace App\Apps\ImageGen\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AiModel;
use App\Models\GenerationTask;
use App\Models\PromptTemplate;
use App\Models\TemplateCategory;
use Illuminate\Http\Request;

class GalleryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int) $request->input('per_page', 12), 50);

        $query = GenerationTask::where('is_public', true)
            ->where('status', 'completed')
            ->with('user:id,name,nickname');

        if ($search = $request->input('q')) {
            $s = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->where('prompt', 'like', "%{$s}%");
        }

        if ($model = $request->input('model')) {
            $query->where('model', $model);
        }

        $tasks = $query->latest()->paginate($perPage);

        $modelLabels = AiModel::where('type', 'image')
            ->pluck('display_name', 'model_id')
            ->all();

        $items = $tasks->getCollection()->filter(function ($task) {
            return !empty($task->items);
        })->map(function ($task) use ($modelLabels) {
            $images = collect($task->items)->filter(fn($i) => !empty($i['url']));
            if ($images->isEmpty()) return null;

            $user = $task->user;
            $urls = $images->pluck('url')->map(fn($u) => self::normalizeImageUrl($u))->values()->all();

            return [
                'task_id' => $task->task_id,
                'prompt' => $task->prompt,
                'model' => $task->model,
                'model_name' => $modelLabels[$task->model] ?? $task->model,
                'size' => $task->size,
                'quality' => $task->quality,
                'image_count' => $images->count(),
                'thumb' => $urls[0] ?? null,
                'images' => $urls,
                'created_at' => $task->created_at?->toIso8601String(),
                'time_ago' => $task->created_at?->diffForHumans(),
                'author' => [
                    'name' => $user?->nickname ?: $user?->name ?: '匿名',
                    'avatar' => $user ? ($user->avatar ?: \App\Models\User::avatarUrlForId($user->id)) : null,
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

    public function templates(Request $request)
    {
        $categories = TemplateCategory::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $query = PromptTemplate::where('status', 'published')->with('category');

        if ($categoryId = $request->input('category')) {
            $query->where('category_id', $categoryId);
        }

        if ($search = $request->input('q')) {
            $s = str_replace(['%', '_'], ['\%', '\_'], $search);
            $query->where(fn ($q) => $q->where('title', 'like', "%{$s}%")->orWhere('tags', 'like', "%{$s}%"));
        }

        $templates = $query->orderByDesc('is_featured')->orderByDesc('sort_order')->limit(200)->get();

        $templateItems = $templates->map(fn ($t) => [
            'id' => $t->id,
            'title' => $t->title,
            'tags' => $t->tags,
            'preview_url' => $t->preview_url,
            'is_featured' => $t->is_featured,
            'category' => $t->category?->name,
            'variables_count' => is_array($t->variables) ? count($t->variables) : 0,
            'has_image_var' => collect($t->variables ?? [])->contains('type', 'image'),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'ok' => true,
                'categories' => $categories,
                'templates' => $templateItems,
            ]);
        }

        return view('explore-templates', [
            'categories' => $categories,
            'templates' => $templateItems,
            'currentCategory' => $categoryId,
        ]);
    }

    public function useTemplate(int $id)
    {
        $template = PromptTemplate::where('status', 'published')->findOrFail($id);

        return view('explore-template-use', [
            'template' => $template,
        ]);
    }

    /**
     * 将数据库中固化的绝对本地存储 URL 转为相对路径，外部 URL 保持不变。
     */
    public static function normalizeImageUrl(?string $url): ?string
    {
        if (!$url) return null;

        // 外部 URL（非本地存储）直接返回
        if (preg_match('#^https?://#', $url)) {
            // 匹配 http(s)://任意域名/storage/... → 转为 /storage/...
            if (preg_match('#^https?://[^/]+(/storage/.+)$#', $url, $m)) {
                return $m[1];
            }
            // 其他外部 URL（如 R2/OSS）保持原样
            return $url;
        }

        // 已经是相对路径
        return $url;
    }
}
