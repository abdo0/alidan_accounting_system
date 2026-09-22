<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Document B §4.2:
 *   Draft -> Submitted -> Reviewed -> Approved -> Posted -> (reverse) Reversed,
 * with reject returning any pre-posting state to Draft.
 */
enum JournalStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Posted = 'posted';
    case Reversed = 'reversed';

    public static function translationKey(): string
    {
        return 'journal_status';
    }

    /**
     * The statuses whose lines are in the ledger. A reversed entry stays in the
     * ledger beside the mirror entry that reverses it -- "both remain visible
     * everywhere" (Document B §2.7) -- and together they net to nil.
     *
     * @return list<string>
     */
    public static function ledgerValues(): array
    {
        return [self::Posted->value, self::Reversed->value];
    }

    public function isInLedger(): bool
    {
        return in_array($this->value, self::ledgerValues(), true);
    }

    /** Statuses a reject returns to Draft from. */
    public function isRejectable(): bool
    {
        return in_array($this, [self::Submitted, self::Reviewed, self::Approved], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::Reviewed => 'warning',
            self::Approved => 'primary',
            self::Posted => 'success',
            self::Reversed => 'danger',
        };
    }
}
