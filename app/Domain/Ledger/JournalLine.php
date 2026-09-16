<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Dimensions\CostCentre;
use App\Domain\Dimensions\Project;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * @property int $id
 * @property int $journal_entry_id
 * @property int $entity_id
 * @property int $fiscal_period_id
 * @property CarbonImmutable $entry_date
 * @property int $line_no
 * @property int $account_id
 * @property int|null $cost_centre_id
 * @property int|null $project_id
 * @property string $currency_code
 * @property string|numeric $debit_amount
 * @property string|numeric $credit_amount
 * @property string|numeric $functional_debit
 * @property string|numeric $functional_credit
 * @property string|null $description
 * @property CarbonImmutable|null $posted_at
 */
class JournalLine extends Model
{
    /**
     * The table has no created_at/updated_at: a ledger line's timestamp is posted_at,
     * and with strict mode on, an insert naming columns that do not exist would fail.
     */
    public $timestamps = false;

    /**
     * The real primary key is (id, entry_date) so the table is partition-ready, but
     * Eloquent cannot address a composite key. A standalone unique on id backs this.
     */
    protected $primaryKey = 'id';

    protected $fillable = [
        'journal_entry_id', 'entity_id', 'fiscal_period_id', 'entry_date', 'line_no',
        'account_id', 'cost_centre_id', 'project_id', 'currency_code', 'exchange_rate',
        'debit_amount', 'credit_amount', 'functional_debit', 'functional_credit',
        'description', 'partner_type', 'partner_id', 'tax_code_id', 'tax_base_amount',
        'quantity', 'uom', 'reconciliation_id', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'immutable_date',
            'posted_at' => 'immutable_datetime',
            'debit_amount' => 'decimal:4',
            'credit_amount' => 'decimal:4',
            'functional_debit' => 'decimal:4',
            'functional_credit' => 'decimal:4',
        ];
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<CostCentre, $this> */
    public function costCentre(): BelongsTo
    {
        return $this->belongsTo(CostCentre::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** Signed movement, debit positive. */
    public function signedAmount(): string
    {
        return bcsub((string) $this->debit_amount, (string) $this->credit_amount, 4);
    }

    public function delete(): ?bool
    {
        if ($this->posted_at !== null) {
            throw new RuntimeException('A posted journal line cannot be deleted.');
        }

        return parent::delete();
    }
}
