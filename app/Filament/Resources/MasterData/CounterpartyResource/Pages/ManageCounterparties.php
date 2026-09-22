<?php

declare(strict_types=1);

namespace App\Filament\Resources\MasterData\CounterpartyResource\Pages;

use App\Filament\Resources\MasterData\CounterpartyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCounterparties extends ManageRecords
{
    protected static string $resource = CounterpartyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
