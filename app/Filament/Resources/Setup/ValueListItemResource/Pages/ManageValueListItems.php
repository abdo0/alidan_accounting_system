<?php

declare(strict_types=1);

namespace App\Filament\Resources\Setup\ValueListItemResource\Pages;

use App\Filament\Resources\Setup\ValueListItemResource;
use Filament\Resources\Pages\ManageRecords;

class ManageValueListItems extends ManageRecords
{
    protected static string $resource = ValueListItemResource::class;
}
