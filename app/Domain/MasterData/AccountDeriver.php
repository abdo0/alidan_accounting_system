<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use App\Domain\MasterData\Enums\AccountType;
use App\Domain\MasterData\Enums\NormalBalance;

/**
 * The system attributes Document B §4.1 derives when the chart is loaded. They are
 * computed once, here, and stored as columns -- the validation engine reads the
 * columns and never carries a list of account codes of its own.
 */
final class AccountDeriver
{
    /** Parents whose children sit at level 3 (Document B §4.1). */
    private const LEVEL_THREE_PARENTS = ['112100', '115030'];

    /**
     * VR-29 asks for a contract on contractor advance, certification, retention and
     * payment entries. These are the accounts those entries touch.
     */
    private const CONTRACT_ACCOUNTS = ['112020', '211002', '211003', '211004'];

    public function level(string $code, ?string $parentCode): int
    {
        if (str_ends_with($code, '000')) {
            return 1;
        }

        return in_array($parentCode, self::LEVEL_THREE_PARENTS, true) ? 3 : 2;
    }

    public function normalBalance(AccountType $type): NormalBalance
    {
        return $type->normalBalance();
    }

    /** 111001 ... 111040 */
    public function isCashAccount(string $code): bool
    {
        return $code >= '111001' && $code <= '111040';
    }

    public function requiresContract(string $code): bool
    {
        return in_array($code, self::CONTRACT_ACCOUNTS, true);
    }

    /**
     * The subledger a control account belongs to, read from the chart's "Control
     * Account (subledger)" text (Document C tab 04).
     */
    public function subledger(string $controlText): ?string
    {
        $text = strtolower($controlText);

        return match (true) {
            $text === '' => null,
            str_contains($text, 'custodian'), str_contains($text, 'employee') => 'custodian',
            str_contains($text, 'personal') => 'personal_receivable',
            str_contains($text, 'government') => 'government',
            str_contains($text, 'shareholder') => 'shareholder',
            str_contains($text, 'contractor'), str_contains($text, 'supplier'), str_contains($text, 'certified'),
            str_contains($text, 'retention') => 'contractor',
            default => null,
        };
    }

    /**
     * Account codes named in a free-text cell such as
     * "Advances 112030 (active) · 112031 (inactive) · Personal receivable 112101".
     *
     * @return list<string>
     */
    public static function codesIn(string $text): array
    {
        preg_match_all('/\b\d{6}\b/', $text, $matches);

        return array_values(array_unique($matches[0]));
    }
}
