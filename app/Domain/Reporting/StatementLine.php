<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $statement_definition_id
 * @property int $sequence
 * @property string $label_ar
 * @property string|null $label_en
 * @property string $line_type
 * @property array<int, string>|null $account_codes
 * @property string|null $formula
 * @property int $sign
 * @property int|null $analytical_ref
 * @property int $indent_level
 * @property bool $is_bold
 */
class StatementLine extends Model
{
    public const HEADER = 'header';

    public const ACCOUNTS = 'accounts';

    public const FORMULA = 'formula';

    public const SUBTOTAL = 'subtotal';

    public const TOTAL = 'total';

    public const NOTE = 'note';

    public const SPACER = 'spacer';

    protected $fillable = [
        'statement_definition_id', 'sequence', 'label_ar', 'label_en', 'line_type',
        'account_codes', 'formula', 'sign', 'analytical_ref', 'indent_level', 'is_bold',
    ];

    protected function casts(): array
    {
        return [
            'account_codes' => 'array',
            'sign' => 'integer',
            'indent_level' => 'integer',
            'is_bold' => 'boolean',
        ];
    }

    /** @return BelongsTo<StatementDefinition, $this> */
    public function statementDefinition(): BelongsTo
    {
        return $this->belongsTo(StatementDefinition::class);
    }

    public function label(): string
    {
        return app()->getLocale() === 'ar' || $this->label_en === null
            ? $this->label_ar
            : $this->label_en;
    }

    public function carriesFigure(): bool
    {
        return in_array($this->line_type, [self::ACCOUNTS, self::FORMULA, self::SUBTOTAL, self::TOTAL], true);
    }
}
