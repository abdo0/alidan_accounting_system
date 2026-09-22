<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organisation\CompanyResource\Pages;

use App\Filament\Resources\Organisation\CompanyResource;
use Filament\Resources\Pages\ListRecords;

class ListCompanies extends ListRecords
{
    protected static string $resource = CompanyResource::class;
}
