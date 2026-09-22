<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funding\FundingBatchResource\Pages;

use App\Filament\Resources\Funding\FundingBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageFundingBatches extends ManageRecords
{
    protected static string $resource = FundingBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
