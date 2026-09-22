<?php

declare(strict_types=1);

namespace App\Filament\Resources\Setup\ChainStepResource\Pages;

use App\Filament\Resources\Setup\ChainStepResource;
use Filament\Resources\Pages\ManageRecords;

class ManageChainSteps extends ManageRecords
{
    protected static string $resource = ChainStepResource::class;
}
