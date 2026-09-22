<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Reports;

use App\Domain\Controls\ControlException;
use App\Domain\Controls\Enums\ExceptionStatus;
use App\Domain\Ledger\Enums\DocStatus;
use App\Domain\Ledger\Enums\JournalStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\Posting\PostingService;
use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\Result\Column;
use App\Domain\Reporting\Result\Control;
use App\Domain\Reporting\Result\ReportResult;
use App\Domain\Reporting\Result\Row;
use App\Models\User;

/**
 * RPT-24 Exceptions and missing documents: live integrity counters, then the
 * exception log. Counters are counted, never stored.
 */
final class Rpt24Exceptions extends BaseReport
{
    public function code(): string
    {
        return 'RPT-24';
    }

    public function filters(): array
    {
        return ['projects', 'from', 'to'];
    }

    public function run(FilterSet $filters, User $user): ReportResult
    {
        $ledger = JournalHeader::query()->whereIn('status', JournalStatus::ledgerValues());

        $counters = [
            __('reports.counters.missing_documents') => (clone $ledger)->where('doc_status', DocStatus::Missing)->count(),
            __('reports.counters.partial_documents') => (clone $ledger)->where('doc_status', DocStatus::Partial)->count(),
            __('reports.counters.undated') => (clone $ledger)->whereNull('txn_date')->count(),
            __('reports.counters.open_exceptions') => ControlException::query()->where('status', '!=', ExceptionStatus::Resolved)->count(),
            __('reports.counters.unposted') => JournalHeader::query()->whereNotIn('status', JournalStatus::ledgerValues())->count(),
        ];

        $rows = [new Row(['subject' => __('reports.counters.title')], Row::GROUP)];
        foreach ($counters as $label => $count) {
            $rows[] = new Row(['subject' => $label, 'count' => (string) $count], Row::DETAIL, [], 1);
        }

        $statuses = [ExceptionStatus::Open->value, ExceptionStatus::UnderReview->value];
        $rows[] = new Row(['subject' => __('resources.exception.plural_label')], Row::GROUP);

        foreach (ControlException::query()->whereIn('status', $statuses)->with('owner')->orderBy('id')->get() as $exception) {
            $rows[] = new Row([
                'no' => $exception->exception_no,
                'category' => $exception->category->getLabel(),
                'subject' => $exception->subject,
                'amount' => $exception->amount,
                'status' => $exception->status->getLabel(),
                'count' => $exception->volume,
                'owner' => $exception->owner?->name,
            ], Row::DETAIL, $exception->journal_header_id === null ? [] : ['*' => ['journal' => $exception->journal_header_id]], 1);
        }

        return new ReportResult(
            code: $this->code(),
            title: $this->title(),
            columns: [
                Column::text('no', __('reports.columns.exception_no')),
                Column::text('category', __('reports.columns.category')),
                Column::text('subject', __('reports.columns.subject')),
                Column::amount('amount', __('reports.columns.amount')),
                Column::text('count', __('reports.columns.volume')),
                Column::text('status', __('reports.columns.status')),
                Column::text('owner', __('reports.columns.owner')),
            ],
            rows: $rows,
            controls: [new Control(__('reports.controls.ledger_balances'), PostingService::ledgerDifference(), 0)],
            filters: $this->describe($filters),
        );
    }
}
