<?php

declare(strict_types=1);

namespace App\Domain\Closing\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/** A closing task: pending, completed, or completed with an exception noted. */
enum ChecklistStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Pending = 'pending';
    case Completed = 'completed';
    case Exception = 'exception';

    public static function translationKey(): string
    {
        return 'checklist_status';
    }
}
