<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funding\FundingSourceResource\Pages;

use App\Filament\Resources\Funding\FundingSourceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFundingSources extends ManageRecords
{
    protected static string $resource = FundingSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
