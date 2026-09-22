<?php

declare(strict_types=1);

namespace Tests\Feature\Ledger;

use App\Domain\Dimensions\CostCentre;
use App\Domain\Ledger\Account;
use App\Domain\Ledger\JournalEntry;
use App\Domain\Ledger\Posting\JournalEntryDraft;
use App\Domain\Ledger\Posting\JournalLineDraft;
use App\Domain\Ledger\Posting\PostingService;
use App\Domain\Organisation\Entity;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\ReferenceSeeder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Codes are the working vocabulary of the Unified Accounting System -- every register in
 * الفصل الخامس has a رقم الدليل column -- so the posting line carries them directly.
 *
 * These tests deliberately exercise the MODEL layer rather than relying on seeded data.
 * Laravel's SeedCommand wraps seeding in Model::unguarded(), so a missing $fillable entry
 * is invisible to any test that only checks what a seeder produced.
 */
class AccountCodeTest extends TestCase
{
    private Entity $entity;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ReferenceSeeder::class);
        $this->entity = Entity::where('code', 'ALIDAN')->firstOrFail();
        $this->accountant = User::factory()->create();
    }

    // ------------------------------------------------- mass assignment reachability

    #[Test]
    public function a_cost_centre_can_be_created_outside_a_seeder(): void
    {
        // Fails if control_class or responsibility_unit are missing from $fillable.
        // Seeders run unguarded, so only a test like this catches it.
        $centre = CostCentre::create([
            'entity_id' => $this->entity->id,
            'code' => 'TEST1',
            'name' => 'Test centre',
            'name_ar' => 'مركز اختبار',
            'cost_centre_type' => CostCentre::PRODUCTION,
            'control_class' => 5,
            'responsibility_unit' => 'Operations',
            'is_postable' => true,
        ]);

        $this->assertSame(5, $centre->fresh()->control_class);
        $this->assertSame('Operations', $centre->fresh()->responsibility_unit);
    }

    #[Test]
    public function the_cost_centre_type_constants_are_valid_under_the_check_constraint(): void
    {
        foreach (CostCentre::CONTROL_CLASSES as $type => $class) {
            $centre = CostCentre::create([
                'entity_id' => $this->entity->id,
                'code' => 'T'.$class,
                'name' => 'Centre '.$class,
                'cost_centre_type' => $type,
                'control_class' => $class,
                'is_postable' => true,
            ]);

            $this->assertSame($type, $centre->fresh()->cost_centre_type);
        }
    }

    // --------------------------------------------------------- composite code

    /**
     * The distribution grid keys on <control class><use element>: ٥٣١ is salaries and
     * wages in production centres.
     */
    #[Test]
    #[DataProvider('compositeCases')]
    public function it_derives_the_composite_statutory_code(string $accountCode, string $centreCode, ?string $expected): void
    {
        $account = Account::where('code', $accountCode)->firstOrFail();
        $centre = CostCentre::where('code', $centreCode)->firstOrFail();

        $this->assertSame($expected, $centre->compositeCodeFor($account));
    }

    /** @return array<string, array{string, string, string|null}> */
    public static function compositeCases(): array
    {
        return [
            'salaries in production' => ['3115', '51', '531'],
            'service requisites, marketing' => ['3352', '71', '733'],
            'service requisites, admin' => ['3352', '81', '833'],
            'depreciation, capital ops' => ['372',  '91', '937'],
            // The grid covers uses only; an asset has no element in it.
            'an asset has no composite' => ['183',  '51', null],
        ];
    }

    #[Test]
    public function the_element_code_is_the_level_two_ancestor(): void
    {
        $this->assertSame('33', Account::where('code', '3352')->firstOrFail()->elementCode());
        $this->assertSame('31', Account::where('code', '3115')->firstOrFail()->elementCode());
        $this->assertNull(Account::where('code', '3')->firstOrFail()->elementCode());
    }

    // --------------------------------------------------------- codes on the line

    #[Test]
    public function posting_stores_both_codes_on_the_line(): void
    {
        $entry = $this->postEntry([
            JournalLineDraft::debit(
                Account::where('code', '3352')->firstOrFail()->id,
                '3000000',
                CostCentre::where('code', '51')->firstOrFail()->id,
            ),
            JournalLineDraft::credit(Account::where('code', '183')->firstOrFail()->id, '3000000'),
        ]);

        $use = $entry->lines->firstWhere('account_code', '3352');
        $bank = $entry->lines->firstWhere('account_code', '183');

        $this->assertNotNull($use, 'The account code must be written at post time.');
        $this->assertSame('533', $use->cost_account_code);

        $this->assertNotNull($bank);
        // Cash is not a use, so it carries no composite code.
        $this->assertNull($bank->cost_account_code);
    }

    #[Test]
    public function a_line_without_a_cost_centre_has_no_composite_code(): void
    {
        $entry = $this->postEntry([
            // Element 35 is exempt from cost-centre classification altogether.
            JournalLineDraft::debit(Account::where('code', '3511')->firstOrFail()->id, '1000000'),
            JournalLineDraft::credit(Account::where('code', '183')->firstOrFail()->id, '1000000'),
        ]);

        $line = $entry->lines->firstWhere('account_code', '3511');

        $this->assertNotNull($line);
        $this->assertNull($line->cost_account_code);
    }

    // --------------------------------------------------------- activity classification

    /**
     * The standard requires distinguishing current from investment activity, and
     * ordinary from exceptional. The columns existed but nothing could write them:
     * they were absent from $fillable and from the draft.
     */
    #[Test]
    public function activity_classification_reaches_the_posted_line(): void
    {
        $entry = $this->postEntry(
            [
                JournalLineDraft::debit(
                    Account::where('code', '3352')->firstOrFail()->id,
                    '500000',
                    CostCentre::where('code', '51')->firstOrFail()->id,
                ),
                JournalLineDraft::credit(Account::where('code', '183')->firstOrFail()->id, '500000'),
            ],
            activityType: 'investment',
            activityNature: 'exceptional',
        );

        foreach ($entry->lines as $line) {
            $this->assertSame('investment', $line->activity_type);
            $this->assertSame('exceptional', $line->activity_nature);
        }
    }

    #[Test]
    public function a_line_may_override_the_document_activity(): void
    {
        $entry = $this->postEntry(
            [
                new JournalLineDraft(
                    accountId: Account::where('code', '3352')->firstOrFail()->id,
                    debit: '500000',
                    costCentreId: CostCentre::where('code', '51')->firstOrFail()->id,
                    activityType: 'investment',
                ),
                JournalLineDraft::credit(Account::where('code', '183')->firstOrFail()->id, '500000'),
            ],
            activityType: 'current',
        );

        $this->assertSame('investment', $entry->lines->firstWhere('account_code', '3352')->activity_type);
        $this->assertSame('current', $entry->lines->firstWhere('account_code', '183')->activity_type);
    }

    /** @param  list<JournalLineDraft>  $lines */
    private function postEntry(array $lines, ?string $activityType = null, ?string $activityNature = null): JournalEntry
    {
        return app(PostingService::class)->post(
            new JournalEntryDraft(
                entityId: $this->entity->id,
                journalCode: 'GJ',
                entryDate: CarbonImmutable::create(2026, 3, 15),
                description: 'Code test',
                lines: $lines,
                sourceDocumentNo: 'DOC-CODE',
                activityType: $activityType,
                activityNature: $activityNature,
            ),
            $this->accountant,
        );
    }
}
