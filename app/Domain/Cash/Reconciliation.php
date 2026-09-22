<?php

declare(strict_types=1);

namespace App\Domain\Cash;

use App\Domain\Cash\Enums\ReconciliationStatus;
use App\Domain\Cash\Enums\ReconciliationType;
use App\Domain\MasterData\BankAccount;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Domain\Shared\Document;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A cash count or bank reconciliation (M11). It records the one figure that comes
 * from outside the ledger -- the actual balance -- with its evidence and the
 * accountant responsible. The book balance and the variance are computed on read
 * (VR-57) and never stored.
 *
 * @property int $id
 * @property ReconciliationType $recon_type
 * @property int|null $bank_account_id
 * @property CarbonImmutable $as_at_date
 * @property int $actual_balance
 * @property int|null $document_id
 * @property int $responsible_user_id
 * @property ReconciliationStatus $status
 * @property int $prepared_by
 * @property int|null $approved_by
 */
class Reconciliation extends Model
{
    use BelongsToCompany;

    protected $table = 'reconciliations';

    protected $fillable = [
        'company_id', 'recon_type', 'bank_account_id', 'counterparty_id', 'account_id', 'period_id', 'as_at_date',
        'actual_balance', 'document_id', 'responsible_user_id', 'status', 'prepared_by', 'prepared_at',
        'reviewed_by', 'reviewed_at', 'approved_by', 'approved_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'recon_type' => ReconciliationType::class,
            'status' => ReconciliationStatus::class,
            'as_at_date' => 'immutable_date',
            'actual_balance' => 'integer',
            'prepared_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    /** @return MorphMany<Document, $this> */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }
}
