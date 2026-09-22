<?php

declare(strict_types=1);

namespace App\Domain\Advances;

use App\Domain\Advances\Enums\EvidenceStatus;
use App\Domain\Advances\Enums\SettlementClassification;
use App\Domain\Advances\Enums\SettlementType;
use App\Domain\Ledger\JournalLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A settlement, refund, reclassification or sub-advance against an advance, linked
 * to the ledger line that records it -- or, for a claim awaiting evidence, the
 * claimed amount alone, which does not reduce the advance.
 *
 * @property int $id
 * @property int $advance_id
 * @property int|null $journal_line_id
 * @property int|null $claimed_amount
 * @property SettlementType $settlement_type
 * @property SettlementClassification|null $classification
 * @property EvidenceStatus|null $evidence_status
 */
class AdvanceSettlement extends Model
{
    protected $table = 'advance_settlements';

    protected $fillable = [
        'advance_id', 'journal_line_id', 'claimed_amount', 'settlement_type', 'classification', 'evidence_status', 'reviewed_by', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'claimed_amount' => 'integer',
            'settlement_type' => SettlementType::class,
            'classification' => SettlementClassification::class,
            'evidence_status' => EvidenceStatus::class,
        ];
    }

    /** @return BelongsTo<Advance, $this> */
    public function advance(): BelongsTo
    {
        return $this->belongsTo(Advance::class, 'advance_id');
    }

    /** @return BelongsTo<JournalLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class, 'journal_line_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
