<?php

declare(strict_types=1);

namespace App\Filament\Resources\Journal\JournalHeaderResource\Pages;

use App\Domain\Ledger\JournalService;
use App\Filament\Resources\Journal\JournalForm;
use App\Filament\Resources\Journal\JournalHeaderResource;
use App\Filament\Support\RuleViolationPresenter;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * REQ-009: the simplified two-account screen for ordinary transactions. It writes
 * exactly what the full form would -- one header and two lines carrying the same
 * dimensions.
 */
class CreateSimpleJournal extends CreateRecord
{
    protected static string $resource = JournalHeaderResource::class;

    public function getTitle(): string
    {
        return __('pages.journal.simple');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([...JournalForm::header(), ...JournalForm::simple()]);
    }

    /** @param  array<string, mixed>  $data */
    protected function handleRecordCreation(array $data): Model
    {
        $header = array_diff_key($data, array_flip(['debit_account_id', 'credit_account_id', 'amount']));
        $dimensions = array_intersect_key($data, array_flip([
            'project_id', 'cost_center_id', 'resp_center_id', 'counterparty_id', 'advance_holder_id', 'funding_source_id',
            'funding_batch_id', 'funding_category_id', 'chain_step_id', 'contract_id', 'capex_opex', 'asset_class',
            'handover_req', 'revenue_eligible', 'eligibility_reason', 'settlement_deadline', 'cash_account_id', 'notes',
        ]));

        $lines = [
            ['account_id' => $data['debit_account_id'], 'debit' => $data['amount'], 'credit' => 0] + $dimensions,
            ['account_id' => $data['credit_account_id'], 'debit' => 0, 'credit' => $data['amount']] + $dimensions,
        ];

        $journal = RuleViolationPresenter::attempt(fn () => app(JournalService::class)->saveDraft(auth()->user(), $header, $lines));

        return $journal ?? throw new Halt;
    }

    protected function getRedirectUrl(): string
    {
        return JournalHeaderResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
