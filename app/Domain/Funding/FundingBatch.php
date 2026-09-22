<?php

declare(strict_types=1);

namespace App\Domain\Funding;

use App\Domain\Funding\Enums\FundingBatchStatus;
use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\Project;
use App\Domain\Shared\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One source funding reference (AMER-nnn, DF-nnn, ...), so every line inherits a traceable batch (RE-04).
 */
class FundingBatch extends Model
{
    use BelongsToCompany;

    protected $table = 'funding_batches';

    protected $fillable = ['company_id', 'batch_ref', 'series', 'funding_source_id', 'counterparty_id', 'project_id', 'batch_date', 'source_amount', 'actual_payment_source', 'source_file', 'source_row', 'status'];

    protected function casts(): array
    {
        return [
            'batch_date' => 'immutable_date',
            'status' => FundingBatchStatus::class,
        ];
    }

    /** @return BelongsTo<FundingSource, $this> */
    public function fundingSource(): BelongsTo
    {
        return $this->belongsTo(FundingSource::class, 'funding_source_id');
    }

    /** @return BelongsTo<Counterparty, $this> */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class, 'counterparty_id');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }
}
