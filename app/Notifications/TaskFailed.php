<?php

namespace App\Notifications;

use App\Models\GenerationTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class TaskFailed extends Notification
{
    use Queueable;

    public function __construct(
        protected GenerationTask $task,
        protected string $error = '',
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'task_failed',
            'task_id' => $this->task->task_id,
            'message' => '图片生成失败：' . $this->error,
            'error' => $this->error,
        ];
    }
}
