<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Reference values used by the ledger but absent from an approved master list load as Pending (CONF-10, CONF-11).
 */
enum ApprovalStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Approved = 'approved';
    case Pending = 'pending';

    public static function translationKey(): string
    {
        return 'approval_status';
    }
}
