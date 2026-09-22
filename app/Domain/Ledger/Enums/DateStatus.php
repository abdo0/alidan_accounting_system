<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * RE-06 / VR-15. The four approved date statuses. An entry whose source carried no
 * date keeps that status and a NULL transaction date: the system never supplies one.
 */
enum DateStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Ok = 'ok';
    case NoDateInSource = 'no_date_in_source';
    case Missing = 'missing';
    case Inconsistent = 'inconsistent';

    public static function translationKey(): string
    {
        return 'date_status';
    }

    /** The value in the authoritative ledger's column N (Document C tab 10). */
    public static function fromSource(string $value): self
    {
        return match (true) {
            $value === 'OK' => self::Ok,
            str_contains($value, 'NO DATE IN SOURCE') => self::NoDateInSource,
            str_starts_with($value, 'Missing Date') => self::Missing,
            str_starts_with($value, 'Inconsistent Date') => self::Inconsistent,
            default => throw new \InvalidArgumentException("Unknown date status \"{$value}\"."),
        };
    }
}
