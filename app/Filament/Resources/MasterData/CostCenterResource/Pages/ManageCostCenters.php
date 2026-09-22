<?php

declare(strict_types=1);

namespace App\Filament\Resources\MasterData\CostCenterResource\Pages;

use App\Filament\Resources\MasterData\CostCenterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCostCenters extends ManageRecords
{
    protected static string $resource = CostCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
