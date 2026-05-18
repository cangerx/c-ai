<?php

namespace App\Filament\Resources\AgentLevelResource\Pages;

use App\Filament\Resources\AgentLevelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgentLevel extends EditRecord
{
    protected static string $resource = AgentLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
