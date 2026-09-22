<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\JournalLine;
use App\Domain\Ledger\Validation\Checkpoint;
use App\Domain\Ledger\Validation\Severity;
use App\Domain\Ledger\Validation\ValidationRule;
use App\Domain\Ledger\Validation\Violation;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\Enums\AccountType;

/**
 * The shared shape of a rule: the full chain runs at Submit and Post, blocking by
 * default, with the message taken from lang/{en,ar}/validation_rules.php under the
 * rule's code.
 */
abstract class BaseRule implements ValidationRule
{
    public function severity(): Severity
    {
        return Severity::Blocking;
    }

    public function appliesAt(Checkpoint $checkpoint): bool
    {
        return $checkpoint->runsFullChain();
    }

    /** @param  array<string, scalar|null>  $params */
    protected function violation(array $params = [], ?JournalLine $line = null, ?string $variant = null): Violation
    {
        $key = 'validation_rules.'.$this->code().($variant === null ? '' : '_'.$variant);

        return new Violation($this->code(), $this->severity(), __($key, $params), $line?->line_no, $params);
    }

    /** Account codes are 6-digit strings, so they compare correctly as strings. */
    protected static function inRange(string $code, string $from, string $to): bool
    {
        return $code >= $from && $code <= $to;
    }

    /**
     * An expense, a Concession-CIP account or a retained fixed asset -- what an
     * advance may never be issued to (VR-25) and what may never be credited to
     * settle one (VR-27). Read from the account's type and statement line, so no
     * code list lives here.
     */
    protected static function isCostOrAsset(Account $account): bool
    {
        return $account->account_type === AccountType::Expense
            || in_array($account->fs_line_code, [self::CIP_LINE, self::FIXED_ASSET_LINE], true);
    }

    /** A trade, other or personal receivable (VR-27): a counterparty asset that is not an advance. */
    protected static function isReceivable(Account $account): bool
    {
        return $account->fs_line_code === self::PERSONAL_RECEIVABLE_LINE
            || ($account->fs_line_code === self::RECEIVABLE_LINE && $account->requires_counterparty && ! $account->is_advance_account);
    }

    /** Funding transactions (VR-20). */
    protected const FUNDING_TYPES = ['TT-01', 'TT-02', 'TT-03', 'TT-36'];

    /** The only transactions that may reduce a shareholder's claim (VR-20, TT-37). */
    protected const LOAN_REDUCING_TYPES = ['TT-37', 'TT-34', 'TT-35', 'TT-39'];

    /** Advance settlements (VR-26, VR-27). */
    protected const SETTLEMENT_TYPES = ['TT-09', 'TT-10', 'TT-11', 'TT-12', 'TT-13'];

    /** Shareholder loans and current accounts (Document C tab 14). */
    protected const SHAREHOLDER_LINES = ['SFP-L-140', 'SFP-L-150'];

    protected const RECEIVABLE_LINE = 'SFP-A-020';

    protected const PERSONAL_RECEIVABLE_LINE = 'SFP-A-025';

    protected const CIP_LINE = 'SFP-A-040';

    protected const FIXED_ASSET_LINE = 'SFP-A-080';
}
