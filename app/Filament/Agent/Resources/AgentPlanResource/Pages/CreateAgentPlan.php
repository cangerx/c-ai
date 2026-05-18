<?php

namespace App\Filament\Agent\Resources\AgentPlanResource\Pages;

use App\Filament\Agent\Resources\AgentPlanResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAgentPlan extends CreateRecord
{
    protected static string $resource = AgentPlanResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['agent_id'] = auth()->id();
        return $data;
    }
}
