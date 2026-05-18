<?php

namespace App\Filament\Resources\PromptTemplateResource\Pages;

use App\Filament\Resources\PromptTemplateResource;
use Filament\Resources\Pages\EditRecord;

class EditPromptTemplate extends EditRecord
{
    protected static string $resource = PromptTemplateResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['status'] = $data['status'] ?? 'draft';
        return $data;
    }
}
