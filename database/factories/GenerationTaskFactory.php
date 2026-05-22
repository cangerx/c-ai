<?php

namespace Database\Factories;

use App\Models\GenerationTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GenerationTask>
 */
class GenerationTaskFactory extends Factory
{
    protected $model = GenerationTask::class;

    public function definition(): array
    {
        return [
            'task_id' => bin2hex(random_bytes(16)),
            'user_id' => User::factory(),
            'status' => 'pending',
            'mode' => 'text',
            'model' => 'gpt-image-2',
            'prompt' => fake()->sentence(),
            'size' => 'auto',
            'quality' => 'medium',
            'count' => 1,
            'is_public' => false,
            'input_count' => 0,
            'items' => [],
            'files' => [],
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }
}
