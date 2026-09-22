<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funding\FundingCategoryResource\Pages;

use App\Filament\Resources\Funding\FundingCategoryResource;
use Filament\Resources\Pages\ManageRecords;

class ManageFundingCategories extends ManageRecords
{
    protected static string $resource = FundingCategoryResource::class;
}
