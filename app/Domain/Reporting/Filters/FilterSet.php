<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Filters;

use App\Domain\Organisation\AccountingPeriod;
use App\Domain\Organisation\Company;
use App\Domain\Organisation\FiscalYear;
use Carbon\CarbonImmutable;

/**
 * The filter engine's state (Document C tab 17). Built from a query string, so a
 * filter set carries unchanged from a parent report into the report it drills into
 * (UAT-046, UAT-047), and printed at the head of every export.
 *
 * Ledger reports filter on the posting date. The transaction date is a separate
 * filter (txn_from / txn_to): an entry whose source carried no date has a posting
 * date but no transaction date, and must not fall out of a trial balance for that.
 */
final class FilterSet
{
    /** Filter key => the F-code of Document C tab 17 it implements. */
    public const CODES = [
        'from' => 'F-01', 'to' => 'F-02', 'fiscal_year' => 'F-03', 'period' => 'F-04',
        'projects' => 'F-06', 'cost_centers' => 'F-07', 'resp_centers' => 'F-08', 'accounts' => 'F-09',
        'account_groups' => 'F-10', 'funding_sources' => 'F-11', 'funding_batches' => 'F-12',
        'funding_categories' => 'F-13', 'chain_steps' => 'F-14', 'shareholders' => 'F-15',
        'counterparties' => 'F-16', 'advance_holders' => 'F-17', 'contracts' => 'F-18', 'work_packages' => 'F-19',
        'capex_opex' => 'F-20', 'asset_classes' => 'F-21', 'handover' => 'F-22', 'statuses' => 'F-23',
        'doc_statuses' => 'F-24', 'recon_statuses' => 'F-25', 'flag_types' => 'F-26', 'date_statuses' => 'F-27',
        'source_presences' => 'F-28', 'cash_accounts' => 'F-29', 'comparative' => 'F-30',
    ];

    /** Keys holding lists. */
    public const LISTS = [
        'projects', 'cost_centers', 'resp_centers', 'accounts', 'account_groups', 'funding_sources', 'funding_batches',
        'funding_categories', 'chain_steps', 'shareholders', 'counterparties', 'advance_holders', 'contracts',
        'work_packages', 'asset_classes', 'handover', 'statuses', 'doc_statuses', 'recon_statuses', 'flag_types',
        'date_statuses', 'source_presences', 'cash_accounts',
    ];

    /** @param  array<string, mixed>  $values */
    private function __construct(private readonly array $values) {}

    /** @param  array<string, mixed>  $input */
    public static function fromArray(array $input): self
    {
        $values = [];

        foreach ([...array_keys(self::CODES), 'txn_from', 'txn_to', 'as_at'] as $key) {
            $value = $input[$key] ?? null;

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $values[$key] = in_array($key, self::LISTS, true)
                ? array_values(array_filter(array_map('strval', (array) $value), fn (string $v): bool => $v !== ''))
                : (string) $value;
        }

        return new self($values);
    }

    /** @param  array<string, mixed>  $overrides */
    public function with(array $overrides): self
    {
        return self::fromArray(array_merge($this->values, $overrides));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->values;
    }

    public function has(string $key): bool
    {
        return isset($this->values[$key]);
    }

    public function get(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @return list<string> */
    public function list(string $key): array
    {
        $value = $this->values[$key] ?? [];

        return is_array($value) ? $value : [$value];
    }

    /** @return list<int> */
    public function ids(string $key): array
    {
        return array_map('intval', $this->list($key));
    }

    /** The reporting range: period, else fiscal year, else from/to, else inception to today. */
    public function from(): CarbonImmutable
    {
        return $this->range()[0];
    }

    public function to(): CarbonImmutable
    {
        return $this->range()[1];
    }

    /** @return array{CarbonImmutable, CarbonImmutable} */
    public function range(): array
    {
        if ($this->has('period')) {
            $period = AccountingPeriod::query()->find((int) $this->get('period'));
            if ($period !== null) {
                return [CarbonImmutable::parse($period->starts_on), CarbonImmutable::parse($period->ends_on)];
            }
        }

        if ($this->has('fiscal_year')) {
            $year = FiscalYear::query()->find((int) $this->get('fiscal_year'));
            if ($year !== null) {
                return [CarbonImmutable::parse($year->starts_on), CarbonImmutable::parse($year->ends_on)];
            }
        }

        $from = $this->has('from') ? CarbonImmutable::parse((string) $this->get('from')) : Company::current()->accounting_start;
        $to = $this->has('to') ? CarbonImmutable::parse((string) $this->get('to')) : ($this->has('as_at') ? CarbonImmutable::parse((string) $this->get('as_at')) : CarbonImmutable::today());

        return [$from->startOfDay(), $to->startOfDay()];
    }

    /** The as-at date of a balance report: the end of the range. */
    public function asAt(): CarbonImmutable
    {
        return $this->has('as_at') ? CarbonImmutable::parse((string) $this->get('as_at'))->startOfDay() : $this->to();
    }
}
