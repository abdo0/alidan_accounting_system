<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of the 39 approved transaction types (Document C tab 12).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $name_ar
 * @property bool $requires_review
 * @property bool $requires_board_approval
 * @property bool $is_system
 * @property bool $is_active
 */
class TransactionType extends Model implements HasDisplayName
{
    use HasTranslatableName;

    public const REVERSAL = 'TT-35';

    public const RECLASSIFICATION = 'TT-34';

    public const MIGRATION = 'TT-39';

    protected $table = 'transaction_types';

    protected $fillable = [
        'code', 'name', 'name_ar', 'business_event', 'debit_logic', 'credit_logic', 'required_dimensions',
        'required_documents', 'approval_path', 'reports_affected', 'control_rules', 'requires_review',
        'requires_board_approval', 'is_system', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'requires_review' => 'boolean',
            'requires_board_approval' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function label(): string
    {
        return $this->code.' — '.$this->displayName();
    }

    public function isCode(string ...$codes): bool
    {
        return in_array($this->code, $codes, true);
    }

    /** @return HasMany<PostingRule, $this> */
    public function postingRules(): HasMany
    {
        return $this->hasMany(PostingRule::class, 'transaction_type_id');
    }
}
