<?php

declare(strict_types=1);

namespace App\Filament\Resources\Access\RoleResource\Pages;

use App\Filament\Resources\Access\RoleResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }
}
