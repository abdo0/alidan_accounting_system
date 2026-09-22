<?php

declare(strict_types=1);

namespace Database\Seeders\Shh;

use App\Domain\Ledger\TransactionType;
use App\Support\Spec\SpecCsv;
use Illuminate\Database\Seeder;

/**
 * The 39 transaction types (Document C tab 12), text carried verbatim.
 *
 * Two attributes are read from the approval path: whether a Senior Accountant
 * reviews before approval, and whether the board must approve. Who approves and
 * posts is the role matrix's decision (tab 19): the Finance Manager, always.
 */
class TransactionTypeSeeder extends Seeder
{
    /** Types with an engine path of their own, never chosen on the entry screen. */
    private const SYSTEM = ['TT-35', 'TT-39'];

    public function run(): void
    {
        $rows = SpecCsv::rows('12_Transaction_Types', [
            'ID', 'Transaction Type (EN)', 'النوع (عربي)', 'Business event', 'Debit logic', 'Credit logic',
            'Required dimensions', 'Required documents', 'Approval workflow', 'Reports affected', 'Control rules',
        ]);

        foreach ($rows as $row) {
            $path = $row['Approval workflow'];

            TransactionType::query()->updateOrCreate(
                ['code' => $row['ID']],
                [
                    'name' => $row['Transaction Type (EN)'],
                    'name_ar' => $row['النوع (عربي)'],
                    'business_event' => $row['Business event'],
                    'debit_logic' => $row['Debit logic'],
                    'credit_logic' => $row['Credit logic'],
                    'required_dimensions' => $row['Required dimensions'],
                    'required_documents' => $row['Required documents'],
                    'approval_path' => $path,
                    'reports_affected' => $row['Reports affected'],
                    'control_rules' => $row['Control rules'],
                    'requires_review' => str_contains($path, 'Senior Accountant'),
                    'requires_board_approval' => str_contains($path, 'board approval required'),
                    'is_system' => in_array($row['ID'], self::SYSTEM, true),
                    'is_active' => true,
                ],
            );
        }
    }
}
