<?php

namespace App\Filament\Resources\PromptTemplateResource\Pages;

use App\Filament\Resources\PromptTemplateResource;
use App\Models\GenerationTask;
use Filament\Resources\Pages\CreateRecord;

class CreatePromptTemplate extends CreateRecord
{
    protected static string $resource = PromptTemplateResource::class;

    public function mount(): void
    {
        parent::mount();

        $taskId = request()->query('task_id');
        if ($taskId && $task = GenerationTask::find($taskId)) {
            $this->form->fill([
                'task_id' => $task->task_id,
                'original_prompt' => $task->prompt,
                'template_prompt' => $task->prompt,
                'preview_url' => collect($task->items ?? [])->pluck('url')->first(),
            ]);
        }
    }
}
