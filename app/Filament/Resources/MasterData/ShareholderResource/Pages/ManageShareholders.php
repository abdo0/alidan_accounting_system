<?php

declare(strict_types=1);

namespace App\Filament\Resources\MasterData\ShareholderResource\Pages;

use App\Filament\Resources\MasterData\ShareholderResource;
use Filament\Resources\Pages\ManageRecords;

class ManageShareholders extends ManageRecords
{
    protected static string $resource = ShareholderResource::class;
}
