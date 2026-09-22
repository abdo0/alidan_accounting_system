<?php

declare(strict_types=1);

namespace App\Domain\Organisation\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Document B §4.11: Open -> Soft Close -> Final Close -> Locked.
 */
enum PeriodStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Open = 'open';
    case SoftClose = 'soft_close';
    case FinalClose = 'final_close';
    case Locked = 'locked';

    public static function translationKey(): string
    {
        return 'period_status';
    }

    /** Ordinary users may post only into an Open period (VR-09). */
    public function acceptsOrdinaryPosting(): bool
    {
        return $this === self::Open;
    }

    /** Final Close and Locked refuse every posting. */
    public function isClosedToEveryone(): bool
    {
        return $this === self::FinalClose || $this === self::Locked;
    }
}
