<?php

declare(strict_types=1);

namespace App\Domain\Controls\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * The three tests of Document B §2.5, plus flags carried in from the source (MIG-14).
 */
enum DuplicateFlagType: string implements HasLabel
{
    use TranslatesEnum;

    case Exact = 'exact';
    case SourceReference = 'source_reference';
    case NearDate = 'near_date';
    case SourceCarried = 'source_carried';

    public static function translationKey(): string
    {
        return 'duplicate_flag_type';
    }
}
