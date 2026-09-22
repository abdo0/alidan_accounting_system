<?php

declare(strict_types=1);

namespace App\Filament\Resources\MasterData\AccountResource\Pages;

use App\Filament\Resources\MasterData\AccountResource;
use Filament\Resources\Pages\ListRecords;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;
}
