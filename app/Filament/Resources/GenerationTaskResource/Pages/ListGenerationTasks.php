<?php

namespace App\Filament\Resources\GenerationTaskResource\Pages;

use App\Filament\Resources\GenerationTaskResource;
use App\Filament\Widgets\GenerationTaskStatsWidget;
use Filament\Resources\Pages\ListRecords;

class ListGenerationTasks extends ListRecords
{
    protected static string $resource = GenerationTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            GenerationTaskStatsWidget::class,
        ];
    }
}
