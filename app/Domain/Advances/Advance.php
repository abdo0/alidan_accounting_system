<?php

declare(strict_types=1);

namespace App\Domain\Advances;

use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\JournalLine;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\Project;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One advance issued to a holder (M04). Its amounts are read from the ledger lines
 * it links: nothing here is a figure.
 *
 * @property int $id
 * @property string $advance_ref
 * @property int $holder_id
 * @property int $account_id
 * @property int|null $issue_line_id
 * @property int|null $issue_journal_id
 * @property CarbonImmutable $issue_date
 * @property CarbonImmutable|null $settlement_deadline
 * @property bool $is_historic
 */
class Advance extends Model
{
    use BelongsToCompany;

    protected $table = 'advances';

    protected $fillable = [
        'company_id', 'advance_ref', 'holder_id', 'account_id', 'project_id', 'cost_center_id', 'resp_center_id',
        'issue_journal_id', 'issue_line_id', 'purpose', 'issue_date', 'settlement_deadline', 'is_historic',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'immutable_date',
            'settlement_deadline' => 'immutable_date',
            'is_historic' => 'boolean',
        ];
    }

    /** @return BelongsTo<Counterparty, $this> */
    public function holder(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'holder_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /** @return BelongsTo<JournalHeader, $this> */
    public function issueJournal(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class, 'issue_journal_id');
    }

    /** @return BelongsTo<JournalLine, $this> */
    public function issueLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class, 'issue_line_id');
    }

    /** @return HasMany<AdvanceSettlement, $this> */
    public function settlements(): HasMany
    {
        return $this->hasMany(AdvanceSettlement::class, 'advance_id')->orderBy('id');
    }
}
