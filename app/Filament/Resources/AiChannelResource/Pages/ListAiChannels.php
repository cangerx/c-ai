<?php

namespace App\Filament\Resources\AiChannelResource\Pages;

use App\Filament\Resources\AiChannelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAiChannels extends ListRecords
{
    protected static string $resource = AiChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
