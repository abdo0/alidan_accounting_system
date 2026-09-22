<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\Ledger\PostingRule;
use App\Domain\Ledger\Rules\MandatoryDimension;
use App\Domain\Ledger\Rules\SelectorParser;
use App\Domain\Ledger\TransactionType;
use App\Domain\MasterData\Enums\ApprovalStatus;
use App\Domain\Organisation\Company;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;

/**
 * The 35 posting rules of Document C tab 13, plus PR-36 ... PR-43 derived from the
 * debit and credit logic of the six transaction types tab 13 does not cover. The
 * derived rules load as Pending: they block their transaction types until the
 * Finance Manager approves them.
 *
 * Selectors are stored as written and in normalised form. A selector or dimension
 * token the parser does not know fails the seed -- a re-issued Document C with new
 * wording is reviewed, not silently mis-read.
 */
class PostingRuleSeeder extends Seeder
{
    public function run(SelectorParser $parser): void
    {
        $types = TransactionType::query()->pluck('id', 'code');
        $from = Company::current()->accounting_start;

        $sources = [
            ['13_Posting_Rules', ApprovalStatus::Approved, 'Document C 13_Posting_Rules'],
            ['derived_posting_rules', null, null],
        ];

        foreach ($sources as [$file, $status, $source]) {
            foreach (SpecCsv::rows($file, ['Rule ID', 'Transaction Type', 'Condition', 'Debit account selector', 'Credit account selector', 'Mandatory dimensions', 'Blocking controls']) as $row) {
                $dimensions = SpecCsv::list($row['Mandatory dimensions']);
                array_map(fn (string $token): MandatoryDimension => MandatoryDimension::fromToken($token), $dimensions);

                PostingRule::query()->updateOrCreate(
                    ['code' => $row['Rule ID']],
                    [
                        'transaction_type_id' => $types[$row['Transaction Type']] ?? throw new \RuntimeException("Unknown transaction type {$row['Transaction Type']}."),
                        'condition' => $row['Condition'],
                        'debit_selector_raw' => $row['Debit account selector'],
                        'credit_selector_raw' => $row['Credit account selector'],
                        'debit_selector' => $parser->normalise($row['Debit account selector']),
                        'credit_selector' => $parser->normalise($row['Credit account selector']),
                        'mandatory_dimensions' => $dimensions,
                        'blocking_rules' => SpecCsv::list($row['Blocking controls']),
                        'effective_from' => $from,
                        'approval_status' => $status ?? ApprovalStatus::from(strtolower($row['Approval Status'])),
                        'source' => $source ?? $row['Derived from'],
                    ],
                );
            }
        }
    }
}
