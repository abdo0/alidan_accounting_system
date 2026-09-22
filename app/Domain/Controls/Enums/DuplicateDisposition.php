<?php

declare(strict_types=1);

namespace App\Domain\Controls\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Document B §2.5. Only Not a Duplicate releases an entry for posting.
 */
enum DuplicateDisposition: string implements HasLabel
{
    use TranslatesEnum;

    case ConfirmedDuplicate = 'confirmed_duplicate';
    case NotADuplicate = 'not_a_duplicate';
    case ManualReviewRequired = 'manual_review_required';
    case HeldUnposted = 'held_unposted';

    public static function translationKey(): string
    {
        return 'duplicate_disposition';
    }

    /** Whether the entry may be posted once every flag carries this disposition. */
    public function releasesPosting(): bool
    {
        return $this === self::NotADuplicate;
    }
}
