<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation;

/**
 * The order of Document B §2.3: rules run so that the user receives the most
 * meaningful message first.
 */
enum RuleGroup: int
{
    case Structural = 1;
    case Account = 2;
    case Period = 3;
    case Dimension = 4;
    case PostingRule = 5;
    case Document = 6;
    case Date = 7;
    case Workflow = 8;
}
