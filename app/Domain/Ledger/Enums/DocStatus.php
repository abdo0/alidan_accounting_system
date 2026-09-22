<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * VR-13: every journal carries its evidence status. Partial and Missing raise an
 * open exception that is closed only when the document arrives.
 */
enum DocStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Complete = 'complete';
    case Partial = 'partial';
    case Missing = 'missing';

    public static function translationKey(): string
    {
        return 'doc_status';
    }

    public function rank(): int
    {
        return match ($this) {
            self::Missing => 0,
            self::Partial => 1,
            self::Complete => 2,
        };
    }
}
