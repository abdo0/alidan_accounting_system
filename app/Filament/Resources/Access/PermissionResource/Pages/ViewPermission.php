<?php

declare(strict_types=1);

namespace App\Filament\Resources\Access\PermissionResource\Pages;

use App\Filament\Resources\Access\PermissionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPermission extends ViewRecord
{
    protected static string $resource = PermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
