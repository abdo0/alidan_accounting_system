<?php

declare(strict_types=1);

namespace App\Filament\Resources\Controls\ControlExceptionResource\Pages;

use App\Domain\Controls\ControlException;
use App\Filament\Resources\Controls\ControlExceptionResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewControlException extends ViewRecord
{
    protected static string $resource = ControlExceptionResource::class;

    protected function resolveRecord(int|string $key): Model
    {
        return ControlException::query()->with(['owner', 'resolver', 'journal', 'comments.user'])->findOrFail($key);
    }
}
