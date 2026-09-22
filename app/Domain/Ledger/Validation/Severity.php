<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation;

/**
 * Document C tab 18: blocking rules prevent the transaction, warning rules need an
 * acknowledgement, automatic rules create a record without user action.
 */
enum Severity: string
{
    case Blocking = 'blocking';
    case Warning = 'warning';
    case Automatic = 'automatic';
}
