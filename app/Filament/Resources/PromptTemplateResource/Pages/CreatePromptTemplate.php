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
        if ($taskId && $task = GenerationTask::where('task_id', $taskId)->first()) {
            $image = collect($task->items ?? [])->firstWhere('url');
            $this->form->fill([
                'task_id' => $task->task_id,
                'original_prompt' => $task->prompt,
                'template_prompt' => $task->prompt,
                'preview_url' => $image['url'] ?? null,
            ]);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['status'] = $data['status'] ?? 'draft';
        return $data;
    }
}
