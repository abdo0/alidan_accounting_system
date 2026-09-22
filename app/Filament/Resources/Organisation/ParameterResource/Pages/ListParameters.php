<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organisation\ParameterResource\Pages;

use App\Filament\Resources\Organisation\ParameterResource;
use Filament\Resources\Pages\ListRecords;

class ListParameters extends ListRecords
{
    protected static string $resource = ParameterResource::class;
}
