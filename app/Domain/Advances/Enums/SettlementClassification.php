<?php

declare(strict_types=1);

namespace App\Domain\Advances\Enums;

use App\Domain\Shared\Concerns\TranslatesEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * The audit working paper's classification of a settlement (Document B §4.3).
 */
enum SettlementClassification: string implements HasLabel
{
    use TranslatesEnum;

    case ValidPrj01 = 'a_valid_prj01';
    case ValidPrj03 = 'b_valid_prj03';
    case PersonalReceivable = 'c_personal_receivable';
    case PendingEvidence = 'd_pending_evidence';

    public static function translationKey(): string
    {
        return 'settlement_classification';
    }
}
