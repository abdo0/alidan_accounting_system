<?php

declare(strict_types=1);

namespace App\Filament\Resources\Journal;

use App\Domain\Funding\ChainStep;
use App\Domain\Funding\FundingBatch;
use App\Domain\Funding\FundingCategory;
use App\Domain\Funding\FundingSource;
use App\Domain\Ledger\Enums\CapexOpex;
use App\Domain\Ledger\Enums\DateStatus;
use App\Domain\Ledger\Enums\DocStatus;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\PostingRule;
use App\Domain\Ledger\TransactionType;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\BankAccount;
use App\Domain\MasterData\Contract;
use App\Domain\MasterData\CostCenter;
use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\Enums\AccountType;
use App\Domain\MasterData\Project;
use App\Domain\MasterData\ResponsibilityCenter;
use App\Domain\MasterData\ValueListItem;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * The entry form. Which dimension fields a line shows follows from its account's
 * flags (Document B §4.1): a cash / bank account for 111xxx lines, the holder for
 * advance lines, eligibility for revenue lines, and so on. The rules themselves are
 * enforced by the validation chain, not by the form.
 */
final class JournalForm
{
    /** @return array<int, Component> */
    public static function header(): array
    {
        return [
            Section::make(__('pages.journal.header'))
                ->columns(3)
                ->schema([
                    Select::make('transaction_type_id')
                        ->label(__('fields.transaction_type'))
                        ->options(fn (): array => TransactionType::query()->where('is_system', false)->where('is_active', true)->orderBy('id')->get()
                            ->mapWithKeys(fn (TransactionType $t): array => [$t->id => $t->label()])->all())
                        ->searchable()
                        ->required()
                        ->live()
                        ->columnSpan(2),
                    Select::make('posting_rule_id')
                        ->label(__('fields.posting_rule'))
                        ->options(fn (Get $get): array => PostingRule::query()->where('transaction_type_id', $get('transaction_type_id'))->orderBy('code')->get()
                            ->mapWithKeys(fn (PostingRule $r): array => [$r->id => $r->code.' — '.$r->condition])->all())
                        ->placeholder('—'),
                    DatePicker::make('posting_date')->label(__('fields.posting_date'))->required()->default(now()),
                    DatePicker::make('txn_date')->label(__('fields.txn_date'))->default(now()),
                    Select::make('date_status')->label(__('fields.date_status'))->options(DateStatus::class)->default(DateStatus::Ok->value)->required(),
                    Textarea::make('description_ar')->label(__('fields.description_ar'))->required()->extraInputAttributes(['dir' => 'rtl'])->columnSpan(2),
                    Textarea::make('description_en')->label(__('fields.description_en')),
                ]),
            Section::make(__('pages.journal.controls'))
                ->columns(3)
                ->collapsible()
                ->schema([
                    Select::make('doc_status')->label(__('fields.doc_status'))->options(DocStatus::class)->default(DocStatus::Missing->value)->required(),
                    TextInput::make('doc_ref')->label(__('fields.doc_ref'))->maxLength(120),
                    TextInput::make('source_reference')->label(__('fields.source_reference'))->maxLength(120),
                    TextInput::make('pv_no')->label(__('fields.pv_no'))->maxLength(30),
                    TextInput::make('rv_no')->label(__('fields.rv_no'))->maxLength(30),
                    TextInput::make('approval_ref')->label(__('fields.approval_ref'))->maxLength(120),
                    TextInput::make('resolution_ref')->label(__('fields.resolution_ref'))->maxLength(120),
                    Select::make('linked_journal_id')->label(__('fields.linked_journal'))
                        ->options(fn (): array => JournalHeader::query()->whereIn('status', JournalStatus::ledgerValues())->orderByDesc('id')->limit(500)->pluck('jv_no', 'id')->all())
                        ->searchable(),
                    Textarea::make('soft_close_reason')->label(__('fields.soft_close_reason')),
                ]),
        ];
    }

    public static function lines(): Repeater
    {
        return Repeater::make('lines')
            ->label(__('pages.journal.lines'))
            ->minItems(2)
            ->defaultItems(2)
            ->reorderable()
            ->columns(4)
            ->itemLabel(fn (array $state): ?string => self::accountLabel($state['account_id'] ?? null))
            ->schema(self::lineFields());
    }

    /** @return array<int, Component> */
    public static function lineFields(): array
    {
        $account = fn (Get $get): ?Account => ($id = $get('account_id')) ? Account::query()->find($id) : null;

        return [
            Select::make('account_id')->label(__('fields.account'))
                ->options(fn (): array => self::postableAccounts())
                ->searchable()->required()->live()->columnSpan(2),
            TextInput::make('debit')->label(__('fields.debit'))->integer()->minValue(0)->default(0),
            TextInput::make('credit')->label(__('fields.credit'))->integer()->minValue(0)->default(0),
            Select::make('project_id')->label(__('fields.project'))->options(fn (): array => Project::query()->where('is_active', true)->pluck('code', 'id')->all())->required(),
            Select::make('cost_center_id')->label(__('fields.cost_center'))
                ->options(fn (): array => CostCenter::query()->where('is_active', true)->orderBy('code')->get()->mapWithKeys(fn (CostCenter $c): array => [$c->id => $c->code.' — '.$c->displayName()])->all())
                ->searchable()->required(),
            Select::make('resp_center_id')->label(__('fields.responsibility_center'))->options(fn (): array => ResponsibilityCenter::query()->pluck('code', 'id')->all()),
            Select::make('counterparty_id')->label(__('fields.counterparty'))->options(fn (): array => self::counterparties())->searchable(),
            Select::make('advance_holder_id')->label(__('fields.advance_holder'))
                ->options(fn (): array => self::counterparties(holdersOnly: true))
                ->visible(fn (Get $get): bool => (bool) $account($get)?->is_advance_account),
            Select::make('cash_account_id')->label(__('fields.cash_account'))
                ->options(fn (Get $get): array => BankAccount::query()->where('account_id', $get('account_id'))->pluck('code', 'id')->all())
                ->visible(fn (Get $get): bool => (bool) $account($get)?->is_cash_account),
            DatePicker::make('settlement_deadline')->label(__('fields.settlement_deadline'))
                ->visible(fn (Get $get): bool => (bool) $account($get)?->is_advance_account),
            Select::make('funding_source_id')->label(__('fields.funding_source'))->options(fn (): array => FundingSource::query()->pluck('code', 'id')->all()),
            Select::make('funding_batch_id')->label(__('fields.funding_batch'))->options(fn (): array => FundingBatch::query()->orderByDesc('id')->limit(500)->pluck('batch_ref', 'id')->all())->searchable(),
            Select::make('funding_category_id')->label(__('fields.funding_category'))->options(fn (): array => FundingCategory::query()->pluck('code', 'id')->all()),
            Select::make('chain_step_id')->label(__('fields.chain_step'))->options(fn (): array => ChainStep::query()->orderBy('sort_order')->pluck('code', 'id')->all()),
            Select::make('contract_id')->label(__('fields.contract_no'))->options(fn (): array => Contract::query()->pluck('contract_no', 'id')->all()),
            Select::make('capex_opex')->label(__('fields.capex_opex'))->options(CapexOpex::class),
            Select::make('asset_class')->label(__('fields.asset_class'))->options(fn (): array => self::valueList('asset_class')),
            Select::make('handover_req')->label(__('fields.handover_req'))->options(fn (): array => self::valueList('handover_requirement')),
            Toggle::make('revenue_eligible')->label(__('fields.revenue_eligible'))
                ->visible(fn (Get $get): bool => $account($get)?->account_type === AccountType::Revenue),
            TextInput::make('eligibility_reason')->label(__('fields.eligibility_reason'))
                ->visible(fn (Get $get): bool => $account($get)?->account_type === AccountType::Revenue),
            TextInput::make('notes')->label(__('fields.notes'))->columnSpan(2),
        ];
    }

    /** @return array<int, Component> */
    public static function simple(): array
    {
        return [
            Section::make(__('pages.journal.simple'))
                ->description(__('pages.journal.simple_help'))
                ->columns(3)
                ->schema([
                    Select::make('debit_account_id')->label(__('pages.journal.debit_account'))->options(fn (): array => self::postableAccounts())->searchable()->required(),
                    Select::make('credit_account_id')->label(__('pages.journal.credit_account'))->options(fn (): array => self::postableAccounts())->searchable()->required(),
                    TextInput::make('amount')->label(__('pages.journal.amount'))->integer()->minValue(1)->required(),
                    Grid::make(3)->columnSpanFull()->schema(array_values(array_filter(
                        self::lineFields(),
                        fn ($field): bool => $field instanceof Field && ! in_array($field->getName(), ['account_id', 'debit', 'credit'], true),
                    ))),
                ]),
        ];
    }

    /** @return array<int, string> */
    public static function postableAccounts(): array
    {
        return Account::query()->postable()->orderBy('code')->get()
            ->mapWithKeys(fn (Account $a): array => [$a->id => $a->label()])->all();
    }

    /** @return array<int, string> */
    private static function counterparties(bool $holdersOnly = false): array
    {
        return Counterparty::query()->where('is_active', true)
            ->when($holdersOnly, fn ($q) => $q->where('is_advance_holder', true))
            ->orderBy('code')->get()
            ->mapWithKeys(fn (Counterparty $c): array => [$c->id => $c->label()])->all();
    }

    /** @return array<string, string> */
    private static function valueList(string $list): array
    {
        return ValueListItem::query()->where('list_code', $list)->orderBy('sort_order')->pluck('value', 'value')->all();
    }

    private static function accountLabel(mixed $id): ?string
    {
        return $id ? Account::query()->find($id)?->label() : null;
    }
}
