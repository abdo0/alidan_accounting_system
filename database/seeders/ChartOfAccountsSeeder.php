<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Ledger\Account;
use App\Domain\Organisation\Entity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Loads the Iraqi Unified Accounting System chart of accounts.
 *
 * Source: النظام المحاسبي الموحد، جمهورية العراق، ديوان الرقابة المالية، الطبعة الثانية ٢٠١١،
 * الفصل الأول (printed pages 16-44 = PDF 17-46). Transcribed from page images because the
 * PDF has no usable text layer -- see docs/10.
 *
 * The parent of an account is its code prefix; there is no parent column, because a
 * separate column could only ever disagree with the code. The seeder validates that
 * relationship rather than trusting it.
 */
class ChartOfAccountsSeeder extends Seeder
{
    private const COLUMNS = [
        'code', 'name_ar', 'name_en', 'account_class', 'account_level',
        'normal_balance', 'statement', 'cash_flow_class', 'is_postable',
        'is_control_account', 'control_subledger', 'requires_cost_centre',
        'is_reconcilable',
    ];

    private const VALID_CLASSES = [
        'asset', 'liability', 'use', 'resource',
        'cc_production', 'cc_prod_services', 'cc_marketing', 'cc_admin', 'cc_capital',
    ];

    /** The 2-digit skeleton printed on pages 17-18. */
    private const EXPECTED_LEVEL_2 = [
        '11', '12', '13', '14', '15', '16', '18', '19',
        '21', '22', '23', '24', '25', '26', '28', '29',
        '31', '32', '33', '34', '35', '36', '37', '38', '39',
        '41', '42', '43', '44', '45', '46', '47', '48', '49',
    ];

    public function run(?string $path = null): void
    {
        $path ??= database_path('data/chart-of-accounts.csv');

        $rows = $this->read($path);
        $this->validate($rows);
        $this->write($rows);
        $this->linkContraPairs();
        $this->enableForEntities();

        $this->command->info(sprintf(
            'Unified Accounting System chart loaded: %d accounts (%d postable).',
            count($rows),
            collect($rows)->where('is_postable', '1')->count(),
        ));
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

    /**
     * Refuses a chart that breaks the standard's own structural rules. A chart that
     * half-imports is worse than one that does not import at all.
     *
     * @param  list<array<string, string>>  $rows
     */
    private function validate(array $rows): void
    {
        $problems = [];
        $codes = array_column($rows, 'code');
        $known = array_flip($codes);

        foreach (array_count_values($codes) as $code => $count) {
            if ($count > 1) {
                $problems[] = "Code {$code} appears {$count} times.";
            }
        }

        foreach ($rows as $row) {
            $code = $row['code'];

            // 1-6 digits, and the digit 0 is never used within a level.
            if (preg_match('/^[1-9]{1,6}$/', $code) !== 1) {
                $problems[] = "Code [{$code}] is not 1-6 digits of 1-9.";

                continue;
            }

            // The parent IS the prefix. This is the standard's own rule.
            if (strlen($code) > 1 && ! isset($known[substr($code, 0, -1)])) {
                $problems[] = "Account {$code} has no parent: "
                    .substr($code, 0, -1).' is absent from the chart.';
            }

            if ((int) $row['account_level'] !== strlen($code)) {
                $problems[] = "Account {$code} declares level {$row['account_level']} "
                    .'but its code is '.strlen($code).' digits.';
            }

            if (! in_array($row['account_class'], self::VALID_CLASSES, true)) {
                $problems[] = "Account {$code} has an unknown class [{$row['account_class']}].";
            }

            // Posting runs from the third level to the leaf.
            if ($row['is_postable'] === '1' && strlen($code) < 3) {
                $problems[] = "Account {$code} is marked postable but sits above level 3.";
            }

            if ($row['is_postable'] === '1' && $this->hasChildren($code, $codes)) {
                $problems[] = "Account {$code} is marked postable but has children.";
            }
        }

        $level2 = array_filter($codes, fn (string $c): bool => strlen($c) === 2);
        $absent = array_diff(self::EXPECTED_LEVEL_2, $level2);

        // Catches a wholly missing subtree, which the parent check cannot: a branch that
        // is absent in its entirety orphans nothing.
        if ($absent !== []) {
            $problems[] = 'Level-2 accounts printed in the standard but absent here: '
                .implode(', ', $absent);
        }

        if ($problems !== []) {
            throw new RuntimeException(
                "The chart of accounts was not imported. Problems found:\n - "
                .implode("\n - ", $problems)
            );
        }
    }

    /** @param  list<string>  $codes */
    private function hasChildren(string $code, array $codes): bool
    {
        foreach ($codes as $other) {
            if (strlen($other) === strlen($code) + 1 && str_starts_with($other, $code)) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<array<string, string>>  $rows */
    private function write(array $rows): void
    {
        // Shallow before deep, so a parent always exists when its child is written.
        usort($rows, fn (array $a, array $b): int => strlen($a['code']) <=> strlen($b['code'])
            ?: strcmp($a['code'], $b['code']));

        $ids = [];

        foreach ($rows as $row) {
            $code = $row['code'];
            $parentCode = strlen($code) > 1 ? substr($code, 0, -1) : null;
            $isControl = $row['is_control_account'] === '1';

            $account = Account::updateOrCreate(
                ['code' => $code],
                [
                    'parent_id' => $parentCode === null ? null : ($ids[$parentCode] ?? null),
                    // The chart is Arabic-native. Where no English gloss exists the
                    // Arabic name stands in both, rather than inventing a translation.
                    'name' => $row['name_en'] !== '' ? $row['name_en'] : $row['name_ar'],
                    'name_ar' => $row['name_ar'],
                    'account_class' => $row['account_class'],
                    'account_level' => (int) $row['account_level'],
                    'normal_balance' => $row['normal_balance'],
                    'statement' => $row['statement'],
                    'cash_flow_class' => $row['cash_flow_class'] !== '' ? $row['cash_flow_class'] : null,
                    'is_postable' => $row['is_postable'] === '1',
                    'is_control_account' => $isControl,
                    'control_subledger' => $row['control_subledger'] !== '' ? $row['control_subledger'] : null,
                    'allow_manual_entry' => ! $isControl,
                    'requires_cost_centre' => $row['requires_cost_centre'] === '1',
                    'is_reconcilable' => $row['is_reconcilable'] === '1',
                    'is_active' => true,
                ]
            );

            $ids[$code] = $account->id;
        }
    }

    /**
     * الحسابات المتقابلة pair 19X <-> 29X by their TRAILING digits. The word مقابل is not
     * a reliable marker of which side is the contra -- on 1922 and 1924 it sits on the
     * opposite side from where the pattern would suggest.
     */
    private function linkContraPairs(): void
    {
        Account::query()
            ->where('statement', 'MEMO')
            ->each(function (Account $account): void {
                $prefix = substr($account->code, 0, 2);

                if (! in_array($prefix, ['19', '29'], true)) {
                    return;
                }

                $partner = ($prefix === '19' ? '29' : '19').substr($account->code, 2);

                if (Account::query()->where('code', $partner)->exists()) {
                    $account->forceFill(['contra_pair_code' => $partner])->save();
                }
            });
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
