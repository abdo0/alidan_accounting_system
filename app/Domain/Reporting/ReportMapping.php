<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\MasterData\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Account -> statement line (Document C tab 15). The only source of statement
 * figures (VR-55).
 *
 * @property int $id
 * @property int $account_id
 * @property string $fs_line_code
 * @property string $statement
 */
class ReportMapping extends Model
{
    protected $table = 'report_mappings';

    protected $fillable = ['account_id', 'fs_line_code', 'statement', 'effective_from', 'effective_to', 'approval_ref'];

    protected function casts(): array
    {
        return [
            'effective_from' => 'immutable_date',
            'effective_to' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /** @return BelongsTo<FsLine, $this> */
    public function line(): BelongsTo
    {
        return $this->belongsTo(FsLine::class, 'fs_line_code', 'code');
    }
}
