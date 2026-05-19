<?php

namespace App\Filament\Resources\AgentSiteResource\Pages;

use App\Filament\Resources\AgentSiteResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateAgentSite extends CreateRecord
{
    protected static string $resource = AgentSiteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = $data['subdomain'];
        $data['status'] = 'approved';
        $data['is_active'] = true;
        $data['approved_at'] = now();
        return $data;
    }

    protected function afterCreate(): void
    {
        User::where('id', $this->record->user_id)
            ->where('role', '!=', 'admin')
            ->update(['role' => 'agent']);
    }
}
