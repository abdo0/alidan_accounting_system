<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Ledger\Account;
use App\Domain\Organisation\Entity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads the chart of accounts from database/data/chart-of-accounts.csv.
 *
 * Data-driven rather than hardcoded because the agreed chart is the Iraqi Unified
 * Accounting System and its official code list is supplied externally. Swapping the
 * chart is then a data change, not a code change touching every report mapping.
 *
 * Validates before it writes: a chart that half-imports is worse than one that does
 * not import at all.
 */
class ChartOfAccountsSeeder extends Seeder
{
    private const COLUMNS = [
        'code', 'name_en', 'name_ar', 'parent_code', 'account_class', 'account_subtype', 'normal_balance',
        'statement', 'cash_flow_class', 'is_postable', 'is_control_account',
        'control_subledger', 'requires_cost_centre', 'is_reconcilable',
    ];

    private const VALID_CLASSES = [
        'asset', 'liability', 'equity', 'revenue', 'cost_of_sales', 'expense',
        'other_income', 'other_expense', 'tax', 'clearing', 'statistical',
    ];

    public function run(?string $path = null): void
    {
        $path ??= database_path('data/chart-of-accounts.csv');

        $rows = $this->read($path);
        $this->validate($rows);
        $this->write($rows);
        $this->enableForEntities();

        $this->command->info(sprintf('Chart of accounts loaded: %d accounts.', count($rows)));
    }

    /** @return list<array<string, string>> */
    private function read(string $path): array
    {
        if (! is_file($path)) {
            throw new RuntimeException("Chart of accounts file not found: {$path}");
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Could not open {$path}");
        }

        $header = fgetcsv($handle, escape: '');

        if ($header === false) {
            throw new RuntimeException('The chart of accounts file is empty.');
        }

        $missing = array_diff(self::COLUMNS, $header);

        if ($missing !== []) {
            throw new RuntimeException(
                'The chart of accounts file is missing columns: '.implode(', ', $missing)
            );
        }

        $rows = [];

        while (($line = fgetcsv($handle, escape: '')) !== false) {
            if ($line === [null]) {
                continue;
            }

            $rows[] = array_combine($header, array_pad($line, count($header), ''));
        }

        fclose($handle);

        return $rows;
    }

    /** @param  list<array<string, string>>  $rows */
    private function validate(array $rows): void
    {
        $problems = [];
        $codes = array_column($rows, 'code');

        foreach (array_count_values($codes) as $code => $count) {
            if ($count > 1) {
                $problems[] = "Code {$code} appears {$count} times; codes must be unique.";
            }
        }

        $known = array_flip($codes);

        foreach ($rows as $row) {
            $code = $row['code'];

            if ($code === '') {
                $problems[] = 'A row has no code.';

                continue;
            }

            if ($row['parent_code'] !== '' && ! isset($known[$row['parent_code']])) {
                $problems[] = "Account {$code} names parent {$row['parent_code']}, which is not in the file.";
            }

            if (! in_array($row['account_class'], self::VALID_CLASSES, true)) {
                $problems[] = "Account {$code} has an unknown class [{$row['account_class']}].";
            }

            if (! in_array($row['normal_balance'], ['D', 'C'], true)) {
                $problems[] = "Account {$code} has an invalid normal balance [{$row['normal_balance']}].";
            }

            if (! in_array($row['statement'], ['BS', 'PL', 'SOCE', 'NONE'], true)) {
                $problems[] = "Account {$code} has an invalid statement [{$row['statement']}].";
            }

            $isControl = $row['is_control_account'] === '1';
            $isPostable = $row['is_postable'] === '1';

            if ($isControl && ! $isPostable) {
                $problems[] = "Account {$code} is a control account but is not postable.";
            }

            if ($isControl && $row['control_subledger'] === '') {
                $problems[] = "Account {$code} is a control account but names no subledger.";
            }

            if ($row['requires_cost_centre'] === '1' && $isControl) {
                $problems[] = "Account {$code} is a control account and must not require a cost centre; "
                    .'the dimension belongs on the revenue or expense side.';
            }
        }

        if ($problems !== []) {
            throw new RuntimeException(
                "The chart of accounts was not imported. Problems found:\n - ".implode("\n - ", $problems)
            );
        }
    }

    /** @param  list<array<string, string>>  $rows */
    private function write(array $rows): void
    {
        // Parents before children, so parent_id can be resolved in one pass.
        usort($rows, fn (array $a, array $b): int => strlen($a['code']) <=> strlen($b['code'])
            ?: strcmp($a['code'], $b['code']));

        $ids = [];

        foreach ($rows as $row) {
            $isControl = $row['is_control_account'] === '1';

            $account = Account::updateOrCreate(
                ['code' => $row['code']],
                [
                    'parent_id' => $row['parent_code'] === '' ? null : ($ids[$row['parent_code']] ?? null),
                    'name' => $row['name_en'],
                    'name_ar' => $row['name_ar'] !== '' ? $row['name_ar'] : null,
                    'account_class' => $row['account_class'],
                    'account_subtype' => $row['account_subtype'] !== '' ? $row['account_subtype'] : null,
                    'normal_balance' => $row['normal_balance'],
                    'statement' => $row['statement'],
                    'cash_flow_class' => $row['cash_flow_class'] !== '' ? $row['cash_flow_class'] : null,
                    'is_postable' => $row['is_postable'] === '1',
                    'is_control_account' => $isControl,
                    'control_subledger' => $row['control_subledger'] !== '' ? $row['control_subledger'] : null,
                    // A control account must never accept a hand-written entry, or the
                    // subledger stops agreeing with it.
                    'allow_manual_entry' => ! $isControl,
                    'requires_cost_centre' => $row['requires_cost_centre'] === '1',
                    'is_reconcilable' => $row['is_reconcilable'] === '1',
                    'is_active' => true,
                ]
            );

            $ids[$row['code']] = $account->id;
        }
    }

    /** V-05 checks this; without it every posting is blocked on day one. */
    private function enableForEntities(): void
    {
        $accountIds = Account::query()->pluck('id');

        Entity::query()->each(function (Entity $entity) use ($accountIds): void {
            $rows = $accountIds->map(fn (int $id): array => [
                'entity_id' => $entity->id,
                'account_id' => $id,
                'is_enabled' => true,
            ])->all();

            if ($rows !== []) {
                DB::table('entity_account_settings')
                    ->upsert($rows, ['entity_id', 'account_id'], ['is_enabled']);
            }
        });
    }
}
