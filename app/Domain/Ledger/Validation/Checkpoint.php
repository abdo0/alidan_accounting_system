<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation;

/** The workflow moments at which the chain runs. */
enum Checkpoint: string
{
    case Save = 'save';
    case Submit = 'submit';
    case Review = 'review';
    case Approve = 'approve';
    case Post = 'post';

    /** Submit and Post run the full chain; Save runs the structural checks only. */
    public function runsFullChain(): bool
    {
        return $this === self::Submit || $this === self::Post;
    }
}
