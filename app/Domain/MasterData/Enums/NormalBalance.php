<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Dr for Asset and Expense, Cr for Liability, Equity and Revenue (Document B §4.1).
 */
enum NormalBalance: string implements HasLabel
{
    use TranslatesEnum;

    case Debit = 'dr';
    case Credit = 'cr';

    public static function translationKey(): string
    {
        return 'normal_balance';
    }

    /** +1 for a debit-normal account, -1 for a credit-normal one. */
    public function sign(): int
    {
        return $this === self::Debit ? 1 : -1;
    }
}
