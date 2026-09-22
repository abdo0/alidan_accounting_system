<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\MasterData\Enums\ApprovalStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A declarative posting rule (Document C tab 13): which accounts a transaction type
 * may debit and credit, and the dimensions it demands. Effective-dated.
 *
 * @property int $id
 * @property string $code
 * @property int $transaction_type_id
 * @property string $condition
 * @property string $debit_selector_raw
 * @property string $credit_selector_raw
 * @property string $debit_selector
 * @property string $credit_selector
 * @property list<string> $mandatory_dimensions
 * @property list<string> $blocking_rules
 * @property CarbonImmutable $effective_from
 * @property CarbonImmutable|null $effective_to
 * @property ApprovalStatus $approval_status
 */
class PostingRule extends Model
{
    protected $table = 'posting_rules';

    protected $fillable = [
        'code', 'transaction_type_id', 'condition', 'debit_selector_raw', 'credit_selector_raw',
        'debit_selector', 'credit_selector', 'mandatory_dimensions', 'blocking_rules',
        'effective_from', 'effective_to', 'approval_status', 'source',
    ];

    protected function casts(): array
    {
        return [
            'mandatory_dimensions' => 'array',
            'blocking_rules' => 'array',
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
            'approval_status' => ApprovalStatus::class,
        ];
    }

    public function isEffectiveOn(\DateTimeInterface $date): bool
    {
        $day = $date->format('Y-m-d');

        return $this->effective_from->toDateString() <= $day
            && ($this->effective_to === null || $this->effective_to->toDateString() > $day);
    }

    /** @return BelongsTo<TransactionType, $this> */
    public function transactionType(): BelongsTo
    {
        return $this->belongsTo(TransactionType::class, 'transaction_type_id');
    }
}
