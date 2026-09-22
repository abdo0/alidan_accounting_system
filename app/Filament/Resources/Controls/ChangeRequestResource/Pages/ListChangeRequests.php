<?php

declare(strict_types=1);

namespace App\Filament\Resources\Controls\ChangeRequestResource\Pages;

use App\Filament\Resources\Controls\ChangeRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListChangeRequests extends ListRecords
{
    protected static string $resource = ChangeRequestResource::class;
}
