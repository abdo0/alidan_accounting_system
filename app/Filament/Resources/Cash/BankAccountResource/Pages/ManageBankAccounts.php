<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cash\BankAccountResource\Pages;

use App\Filament\Resources\Cash\BankAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageBankAccounts extends ManageRecords
{
    protected static string $resource = BankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
