<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * The company, value lists and chain steps are the approved specification itself.
 * They change by re-issuing Document C, not through a form.
 */
class ReferenceDataPolicy extends ReadOnlyPolicy
{
    protected function viewPermission(): string
    {
        return 'masterdata.view';
    }
}
