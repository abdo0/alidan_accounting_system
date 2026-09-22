<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use Carbon\CarbonImmutable;

/**
 * PARAM-010 reads "May 2023 / أيار 2023". The accounting start is the first day of
 * that month.
 */
final class AccountingStart
{
    public static function fromSpec(string $value): CarbonImmutable
    {
        $english = trim(explode('/', $value)[0]);

        return CarbonImmutable::createFromFormat('!F Y', $english)->startOfMonth();
    }
}
