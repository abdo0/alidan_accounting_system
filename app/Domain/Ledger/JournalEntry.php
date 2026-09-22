<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Organisation\Entity;
use App\Domain\Organisation\FiscalPeriod;
use App\Domain\Shared\Attachment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use RuntimeException;

/**
 * @property int $id
 * @property int $entity_id
 * @property int $journal_id
 * @property int $fiscal_period_id
 * @property string|null $entry_no
 * @property CarbonImmutable $entry_date
 * @property CarbonImmutable $posting_date
 * @property string $description
 * @property string $currency_code
 * @property string $source_type
 * @property int|null $source_id
 * @property string|null $source_document_no
 * @property string $status
 * @property bool $is_adjusting
 * @property bool $is_closing
 * @property bool $is_opening
 * @property bool $is_system_generated
 * @property int|null $reverses_entry_id
 * @property int|null $reversed_by_entry_id
 * @property string|null $reversal_reason
 * @property string|numeric $total_debit
 * @property string|numeric $total_credit
 * @property int $created_by
 * @property int|null $approved_by
 * @property int|null $posted_by
 * @property CarbonImmutable|null $posted_at
 * @property string|null $entry_hash
 * @property string|null $prev_entry_hash
 */
class JournalEntry extends Model
{
    public const DRAFT = 'draft';

    public const PENDING_APPROVAL = 'pending_approval';

    public const APPROVED = 'approved';

    public const POSTED = 'posted';

    public const REVERSED = 'reversed';

    public const REJECTED = 'rejected';

    protected $fillable = [
        'entity_id', 'journal_id', 'fiscal_period_id', 'entry_no', 'entry_date',
        'posting_date', 'description', 'description_ar', 'currency_code',
        'exchange_rate', 'source_type', 'source_id', 'source_document_no',
        'source_document_date', 'status', 'is_adjusting', 'is_closing', 'is_opening',
        'is_system_generated', 'reverses_entry_id', 'reversed_by_entry_id',
        'reversal_reason', 'auto_reverse_on', 'total_debit', 'total_credit',
        'created_by', 'approved_by', 'approved_at', 'posted_by', 'posted_at',
        'entry_hash', 'prev_entry_hash',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'immutable_date',
            'posting_date' => 'immutable_date',
            'source_document_date' => 'immutable_date',
            'auto_reverse_on' => 'immutable_date',
            'approved_at' => 'immutable_datetime',
            'posted_at' => 'immutable_datetime',
            'is_adjusting' => 'boolean',
            'is_closing' => 'boolean',
            'is_opening' => 'boolean',
            'is_system_generated' => 'boolean',
            'total_debit' => 'decimal:4',
            'total_credit' => 'decimal:4',
        ];
    }

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('line_no');
    }

    /** @return BelongsTo<Entity, $this> */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /** @return BelongsTo<FiscalPeriod, $this> */
    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(FiscalPeriod::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_entry_id');
    }

    /** @return MorphMany<Attachment, $this> */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function isPosted(): bool
    {
        return in_array($this->status, [self::POSTED, self::REVERSED], true);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::DRAFT, self::REJECTED], true);
    }

    /**
     * Posted entries are append-only. Deleting one is blocked by a database rule as
     * well; this makes the intent explicit at the model layer rather than letting a
     * delete succeed silently as a no-op.
     */
    public function delete(): ?bool
    {
        if ($this->isPosted()) {
            throw new RuntimeException(
                "Journal entry {$this->entry_no} is posted and cannot be deleted. Reverse it instead."
            );
        }

        return parent::delete();
    }
}
