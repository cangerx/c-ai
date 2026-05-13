<?php

namespace App\Notifications;

use App\Models\GenerationTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskCompleted extends Notification
{
    use Queueable;

    public function __construct(protected GenerationTask $task) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'task_completed',
            'task_id' => $this->task->task_id,
            'message' => '图片生成完成',
            'image_count' => count($this->task->items ?? []),
            'thumb' => $this->task->items[0]['url'] ?? null,
        ];
    }
}
