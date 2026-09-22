<?php

declare(strict_types=1);

namespace App\Filament\Resources\Access\PermissionResource\Pages;

use App\Filament\Resources\Access\PermissionResource;
use Filament\Resources\Pages\ListRecords;

class ListPermissions extends ListRecords
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
