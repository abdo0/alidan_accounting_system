<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * Fiscal years and periods come from the calendar seeder. Status changes are the
 * closing actions of M15, each with its own permission; the records themselves are
 * never created or edited through a form.
 */
class PeriodPolicy extends ReadOnlyPolicy
{
    protected function viewPermission(): string
    {
        return 'period.view';
    }
}
