<?php

namespace App\Filament\Agent\Resources\AgentPlanResource\Pages;

use App\Filament\Agent\Resources\AgentPlanResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions;

class ListAgentPlans extends ListRecords
{
    protected static string $resource = AgentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
