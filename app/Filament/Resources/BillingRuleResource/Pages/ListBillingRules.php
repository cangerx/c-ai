<?php

namespace App\Filament\Resources\BillingRuleResource\Pages;

use App\Filament\Resources\BillingRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBillingRules extends ListRecords
{
    protected static string $resource = BillingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()];
    }
}
