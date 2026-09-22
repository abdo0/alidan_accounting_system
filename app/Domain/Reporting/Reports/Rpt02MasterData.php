<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\MasterData\BankAccount;
use App\Domain\MasterData\Contract;
use App\Domain\MasterData\CostCenter;
use App\Domain\MasterData\Counterparty;
use App\Domain\MasterData\Project;
use App\Domain\MasterData\ResponsibilityCenter;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** RPT-02 Master data register: every dimension and party, in one listing. */
final class Rpt02MasterData extends BaseReport
{
    public function code(): string
    {
        return 'RPT-02';
    }

    public function filters(): array
    {
        return [];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $rows = [];

        foreach ([
            'project' => Project::query()->orderBy('code')->get(),
            'cost_center' => CostCenter::query()->orderBy('code')->get(),
            'responsibility_center' => ResponsibilityCenter::query()->orderBy('code')->get(),
            'counterparty' => Counterparty::query()->orderBy('code')->get(),
            'bank_account' => BankAccount::query()->orderBy('code')->get(),
            'contract' => Contract::query()->orderBy('contract_no')->get(),
        ] as $kind => $records) {
            $rows[] = new Row(['kind' => __('resources.'.$kind.'.plural_label')], Row::GROUP);

            foreach ($records as $record) {
                $rows[] = new Row([
                    'kind' => __('resources.'.$kind.'.label'),
                    'code' => (string) ($record instanceof Contract ? $record->contract_no : $record->getAttribute('code')),
                    'name' => $this->nameOf($record),
                    'active' => (array_key_exists('is_active', $record->getAttributes()) ? (bool) $record->getAttribute('is_active') : true) ? __('reports.yes') : __('reports.no'),
                ], Row::DETAIL, [], 1);
            }
        }

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('kind', __('reports.columns.type')),
                Column::text('code', __('reports.columns.code')),
                Column::text('name', __('reports.columns.name')),
                Column::text('active', __('reports.columns.active')),
            ],
            rows: $rows,
            filters: [],
        );
    }

    private function nameOf(Model $record): string
    {
        if (method_exists($record, 'displayName')) {
            return $record->displayName();
        }

        if ($record instanceof Contract) {
            return (string) (app()->getLocale() === 'ar' && $record->title_ar ? $record->title_ar : $record->title);
        }

        return (string) $record->getKey();
    }
}
