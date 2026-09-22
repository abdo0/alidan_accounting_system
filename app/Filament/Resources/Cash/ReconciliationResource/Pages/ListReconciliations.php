<?php

declare(strict_types=1);

namespace App\Filament\Resources\Cash\ReconciliationResource\Pages;

use App\Filament\Resources\Cash\ReconciliationResource;
use Filament\Resources\Pages\ListRecords;

class ListReconciliations extends ListRecords
{
    protected static string $resource = ReconciliationResource::class;
}
