<?php

declare(strict_types=1);

namespace App\Domain\Ledger;

use App\Domain\Shared\Concerns\HasTranslatableName;
use Illuminate\Database\Eloquent\Model;

/**
 * A book of prime entry. The six named books plus the system journals every later
 * module posts through.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $journal_type
 * @property bool $allows_manual_entry
 * @property bool $is_system
 * @property string $sequence_prefix
 * @property bool $is_active
 */
class Journal extends Model
{
    use HasTranslatableName;

    public const SALES = 'SDB';

    public const PURCHASES = 'PDB';

    public const RETURNS_IN = 'RIB';

    public const RETURNS_OUT = 'ROB';

    public const CASH = 'CB';

    public const PETTY_CASH = 'PCB';

    public const GENERAL = 'GJ';

    public const PAYROLL = 'PAY';

    public const FIXED_ASSETS = 'FA';

    public const INVENTORY = 'INV';

    public const ALLOCATION = 'ALC';

    public const CLOSING = 'CLO';

    public const OPENING = 'OPN';

    protected $fillable = [
        'code', 'name', 'name_ar', 'journal_type', 'default_debit_account_id',
        'default_credit_account_id', 'allows_manual_entry', 'is_system',
        'sequence_prefix', 'display_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'allows_manual_entry' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
