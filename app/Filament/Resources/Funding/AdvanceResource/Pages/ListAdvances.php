<?php

declare(strict_types=1);

namespace App\Filament\Resources\Funding\AdvanceResource\Pages;

use App\Filament\Resources\Funding\AdvanceResource;
use Filament\Resources\Pages\ListRecords;

class ListAdvances extends ListRecords
{
    protected static string $resource = AdvanceResource::class;
}
