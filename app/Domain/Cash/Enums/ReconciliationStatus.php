<?php

declare(strict_types=1);

namespace App\Domain\Cash\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Prepared, then approved by someone else.
 */
enum ReconciliationStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';

    public static function translationKey(): string
    {
        return 'reconciliation_status';
    }
}
