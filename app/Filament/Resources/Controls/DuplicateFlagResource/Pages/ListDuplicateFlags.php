<?php

declare(strict_types=1);

namespace App\Filament\Resources\Controls\DuplicateFlagResource\Pages;

use App\Filament\Resources\Controls\DuplicateFlagResource;
use Filament\Resources\Pages\ListRecords;

class ListDuplicateFlags extends ListRecords
{
    protected static string $resource = DuplicateFlagResource::class;
}
