<?php

declare(strict_types=1);

namespace App\Domain\Controls;

use App\Domain\Controls\Enums\DuplicateDisposition;
use App\Domain\Controls\Enums\DuplicateFlagType;
use App\Domain\Ledger\JournalHeader;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A duplicate suspicion (Document B §2.5). Never deleted: the disposition is the
 * record.
 *
 * @property int $id
 * @property int $journal_header_id
 * @property int|null $matched_journal_id
 * @property DuplicateFlagType $flag_type
 * @property string $match_reason
 * @property int $score
 * @property int $value_at_risk
 * @property DuplicateDisposition|null $disposition
 * @property int|null $dispositioned_by
 * @property string|null $source_review
 */
class DuplicateFlag extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'duplicate_flags';

    protected $fillable = [
        'journal_header_id', 'matched_journal_id', 'flag_type', 'match_reason', 'score', 'value_at_risk',
        'disposition', 'dispositioned_by', 'dispositioned_at', 'disposition_note', 'source_review', 'origin',
    ];

    protected function casts(): array
    {
        return [
            'flag_type' => DuplicateFlagType::class,
            'disposition' => DuplicateDisposition::class,
            'score' => 'integer',
            'value_at_risk' => 'integer',
            'dispositioned_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<JournalHeader, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class, 'journal_header_id');
    }

    /** @return BelongsTo<JournalHeader, $this> */
    public function matchedJournal(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class, 'matched_journal_id');
    }

    /** @return BelongsTo<User, $this> */
    public function dispositioner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispositioned_by');
    }
}
