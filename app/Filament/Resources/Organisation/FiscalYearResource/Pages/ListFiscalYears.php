<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organisation\FiscalYearResource\Pages;

use App\Filament\Resources\Organisation\FiscalYearResource;
use Filament\Resources\Pages\ListRecords;

class ListFiscalYears extends ListRecords
{
    protected static string $resource = FiscalYearResource::class;
}
