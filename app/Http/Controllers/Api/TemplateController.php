<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PromptTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PromptTemplate::where('status', 'published');

        if ($tag = $request->query('tag')) {
            $t = str_replace(['%', '_'], ['\%', '\_'], $tag);
            $query->where('tags', 'like', "%{$t}%");
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        $templates = $query->orderByDesc('is_featured')
            ->orderByDesc('sort_order')
            ->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json($templates);
    }

    public function show(PromptTemplate $template): JsonResponse
    {
        if ($template->status !== 'published') {
            abort(404);
        }
        return response()->json($template);
    }

    public function build(Request $request, PromptTemplate $template): JsonResponse
    {
        if ($template->status !== 'published') {
            abort(404);
        }

        $values = $request->validate([
            'variables' => 'required|array',
        ])['variables'];

        return response()->json([
            'prompt' => $template->buildPrompt($values),
        ]);
    }
}
