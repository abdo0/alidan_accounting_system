<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/** What an approvals row records. */
enum ApprovalAction: string implements HasLabel
{
    use TranslatesEnum;

    case Submit = 'submit';
    case Review = 'review';
    case Approve = 'approve';
    case Reject = 'reject';
    case Post = 'post';
    case Reverse = 'reverse';
    case Reclassify = 'reclassify';
    case BoardDecision = 'board_decision';
    case SoftClose = 'soft_close';
    case FinalClose = 'final_close';
    case Reopen = 'reopen';
    case Lock = 'lock';

    public static function translationKey(): string
    {
        return 'approval_action';
    }
}
