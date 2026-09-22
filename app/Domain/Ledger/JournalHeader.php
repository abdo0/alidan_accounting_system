<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Ledger\Enums\DateStatus;
use App\Domain\Ledger\Enums\DocStatus;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Shared\Approval;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Shared\Document;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One accounting transaction (Document B §3.2). It carries no totals: every figure
 * is the sum of its lines.
 *
 * @property int $id
 * @property int $company_id
 * @property int $period_id
 * @property string|null $jv_no
 * @property string|null $pv_no
 * @property string|null $rv_no
 * @property int $transaction_type_id
 * @property int|null $posting_rule_id
 * @property CarbonImmutable|null $txn_date
 * @property CarbonImmutable $posting_date
 * @property string $description_ar
 * @property string|null $description_en
 * @property DateStatus $date_status
 * @property DocStatus $doc_status
 * @property string|null $doc_ref
 * @property string|null $source_reference
 * @property string|null $source_file
 * @property string|null $source_row
 * @property JournalStatus $status
 * @property bool $is_migration
 * @property int|null $reversal_of_journal_id
 * @property int|null $reversed_by_journal_id
 * @property int|null $linked_journal_id
 * @property string|null $approval_ref
 * @property string|null $resolution_ref
 * @property string|null $reversal_reason
 * @property string|null $soft_close_reason
 * @property array<int, string>|null $acknowledged_warnings
 * @property int $created_by
 * @property int|null $reviewed_by
 * @property int|null $approved_by
 * @property int|null $posted_by
 * @property string|null $entry_hash
 * @property string|null $prev_entry_hash
 * @property-read Collection<int, JournalLine> $lines
 * @property-read TransactionType $transactionType
 * @property-read AccountingPeriod $period
 */
class JournalHeader extends Model
{
    use BelongsToCompany;

    protected $table = 'journal_headers';

    protected $fillable = [
        'company_id', 'period_id', 'jv_no', 'pv_no', 'rv_no', 'transaction_type_id', 'posting_rule_id',
        'txn_date', 'posting_date', 'description_ar', 'description_en', 'date_status', 'doc_status', 'doc_ref',
        'recon_status', 'source_presence', 'source_reference', 'source_file', 'source_row', 'status',
        'is_migration', 'migration_run_id', 'reversal_of_journal_id', 'reversed_by_journal_id', 'linked_journal_id',
        'approval_ref', 'resolution_ref', 'reversal_reason', 'soft_close_reason', 'acknowledged_warnings',
        'rejection_comment', 'created_by', 'submitted_by', 'submitted_at', 'reviewed_by', 'reviewed_at',
        'approved_by', 'approved_at', 'posted_by', 'posted_at', 'rejected_by', 'rejected_at',
        'entry_hash', 'prev_entry_hash', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'txn_date' => 'immutable_date',
            'posting_date' => 'immutable_date',
            'date_status' => DateStatus::class,
            'doc_status' => DocStatus::class,
            'status' => JournalStatus::class,
            'is_migration' => 'boolean',
            'acknowledged_warnings' => 'array',
            'submitted_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'posted_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
        ];
    }

    public function totalDebit(): int
    {
        return (int) $this->lines->sum('debit');
    }

    public function totalCredit(): int
    {
        return (int) $this->lines->sum('credit');
    }

    public function isEditable(): bool
    {
        return $this->status === JournalStatus::Draft;
    }

    public function reference(): string
    {
        return $this->jv_no ?? '#'.$this->id;
    }

    /** @param  Builder<JournalHeader>  $query */
    public function scopeInLedger(Builder $query): void
    {
        $query->whereIn('status', JournalStatus::ledgerValues());
    }

    /** @return HasMany<JournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class, 'journal_header_id')->orderBy('line_no');
    }

    /** @return BelongsTo<TransactionType, $this> */
    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }

    /** @return BelongsTo<PostingRule, $this> */
    public function postingRule(): BelongsTo
    {
        return $this->belongsTo(PostingRule::class, 'posting_rule_id');
    }

    /** @return BelongsTo<AccountingPeriod, $this> */
    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    /** @return BelongsTo<JournalHeader, $this> */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_journal_id');
    }

    /** @return BelongsTo<JournalHeader, $this> */
    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_by_journal_id');
    }

    /** @return BelongsTo<JournalHeader, $this> */
    public function linkedJournal(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_journal_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /** @return HasMany<Approval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class, 'object_id')->where('object_type', $this->getTable())->orderBy('id');
    }
}
