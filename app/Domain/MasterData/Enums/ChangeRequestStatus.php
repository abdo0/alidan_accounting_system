<?php

declare(strict_types=1);

namespace App\Domain\MasterData\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Maker-checker on master data (VR-60, RE-11).
 */
enum ChangeRequestStatus: string implements HasLabel
{
    use TranslatesEnum;

    case Proposed = 'proposed';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Applied = 'applied';

    public static function translationKey(): string
    {
        return 'change_request_status';
    }
}
