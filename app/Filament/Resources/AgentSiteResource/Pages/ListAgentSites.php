<?php

namespace App\Filament\Resources\AgentSiteResource\Pages;

use App\Filament\Resources\AgentSiteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAgentSites extends ListRecords
{
    protected static string $resource = AgentSiteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()->label('新建分站'),
        ];
    }
}
