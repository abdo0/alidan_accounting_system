<?php

declare(strict_types=1);

namespace App\Filament\Resources\MasterData\ContractResource\Pages;

use App\Filament\Resources\MasterData\ContractResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageContracts extends ManageRecords
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
