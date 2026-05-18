<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptTemplate extends Model
{
    protected $fillable = [
        'category_id', 'task_id', 'title', 'original_prompt', 'template_prompt',
        'variables', 'tags', 'preview_url', 'is_featured', 'sort_order', 'status',
    ];

    protected $attributes = [
        'sort_order' => 0,
        'status' => 'draft',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_featured' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $template) {
            if (!$template->template_prompt) return;

            preg_match_all('/\{\{(\w+)\}\}/', $template->template_prompt, $matches);
            $promptVars = array_unique($matches[1]);

            $existing = collect($template->variables ?? []);
            $existingNames = $existing->pluck('name')->toArray();

            foreach ($promptVars as $name) {
                if (!in_array($name, $existingNames)) {
                    $existing->push([
                        'name' => $name,
                        'type' => 'text',
                        'label' => str_replace('_', ' ', ucfirst($name)),
                        'default' => '',
                        'description' => '',
                        'alternatives' => [],
                    ]);
                }
            }

            $template->variables = $existing->values()->toArray();
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TemplateCategory::class, 'category_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(GenerationTask::class, 'task_id', 'task_id');
    }

    public function buildPrompt(array $values): string
    {
        $prompt = $this->template_prompt;
        foreach ($this->variables ?? [] as $var) {
            $value = $values[$var['name']] ?? $var['default'] ?? '';
            $prompt = str_replace('{{' . $var['name'] . '}}', $value, $prompt);
        }
        return $prompt;
    }
}
