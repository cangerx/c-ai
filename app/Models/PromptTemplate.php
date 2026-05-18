<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptTemplate extends Model
{
    protected $fillable = [
        'task_id', 'title', 'original_prompt', 'template_prompt',
        'variables', 'tags', 'preview_url', 'is_featured', 'sort_order', 'status',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_featured' => 'boolean',
    ];

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
