<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organisation\ChecklistTaskResource\Pages;

use App\Filament\Resources\Organisation\ChecklistTaskResource;
use Filament\Resources\Pages\ListRecords;

class ListChecklistTasks extends ListRecords
{
    protected static string $resource = ChecklistTaskResource::class;
}
