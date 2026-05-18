<?php

namespace App\Filament\Resources\AgentSiteResource\Pages;

use App\Filament\Resources\AgentSiteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAgentSite extends EditRecord
{
    protected static string $resource = AgentSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
