<?php

declare(strict_types=1);

namespace App\Domain\Controls;

use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\Controls\Enums\ExceptionStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\Counterparty;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An entry of the exceptions register (M09, Document C tab 24). Resolved only with
 * a resolution and a resolver; never deleted (VR-59).
 *
 * @property int $id
 * @property string $exception_no
 * @property string|null $source_code
 * @property ExceptionCategory $category
 * @property string $subject
 * @property int|null $amount
 * @property ExceptionStatus $status
 * @property int|null $owner_id
 * @property string|null $resolution
 * @property int|null $journal_header_id
 * @property bool $blocks_final_close
 * @property int|null $fiscal_year_id
 */
class ControlException extends Model
{
    use BelongsToCompany;

    protected $table = 'exceptions';

    protected $fillable = [
        'company_id', 'exception_no', 'source_code', 'category', 'raised_date', 'subject', 'description', 'description_ar',
        'amount', 'volume', 'status', 'owner_id', 'raised_by', 'required_action', 'proposed_resolution', 'resolution',
        'resolved_by', 'resolved_at', 'journal_header_id', 'journal_line_id', 'account_id', 'counterparty_id',
        'fiscal_year_id', 'blocks_final_close', 'dedupe_key',
    ];

    protected function casts(): array
    {
        return [
            'category' => ExceptionCategory::class,
            'status' => ExceptionStatus::class,
            'amount' => 'integer',
            'raised_date' => 'immutable_date',
            'resolved_at' => 'immutable_datetime',
            'blocks_final_close' => 'boolean',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status !== ExceptionStatus::Resolved;
    }

    /** @return BelongsTo<JournalHeader, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalHeader::class, 'journal_header_id');
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** @return BelongsTo<Counterparty, $this> */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /** @return HasMany<ExceptionComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(ExceptionComment::class, 'exception_id')->orderBy('id');
    }
}
