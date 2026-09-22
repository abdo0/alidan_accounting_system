<?php

declare(strict_types=1);

namespace App\Domain\Advances\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * The evidence behind a settlement claim.
 */
enum EvidenceStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Complete = 'complete';
    case Partial = 'partial';
    case Missing = 'missing';
    case Pending = 'pending';

    public static function translationKey(): string
    {
        return 'evidence_status';
    }
}
