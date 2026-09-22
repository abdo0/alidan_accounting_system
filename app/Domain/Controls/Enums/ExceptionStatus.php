<?php

declare(strict_types=1);

namespace App\Domain\Controls\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Document B §4.7: Open -> Under Review -> Resolved. Never deleted (VR-59).
 */
enum ExceptionStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Open = 'open';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';

    public static function translationKey(): string
    {
        return 'exception_status';
    }
}
