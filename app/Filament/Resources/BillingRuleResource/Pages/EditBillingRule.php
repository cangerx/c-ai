<?php

namespace App\Filament\Resources\BillingRuleResource\Pages;

use App\Filament\Resources\BillingRuleResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBillingRule extends EditRecord
{
    protected static string $resource = BillingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()];
    }
}
