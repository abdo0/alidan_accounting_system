<?php

declare(strict_types=1);

namespace App\Filament\Resources\MasterData\ResponsibilityCenterResource\Pages;

use App\Filament\Resources\MasterData\ResponsibilityCenterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageResponsibilityCenters extends ManageRecords
{
    protected static string $resource = ResponsibilityCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
