<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use App\Domain\Cash\BookBalance;
use App\Domain\Cash\ReconciliationService;
use App\Domain\Controls\ControlException;
use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\MasterData\BankAccount;
use App\Domain\Shared\Exceptions\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\BuildsJournals;
use Tests\TestCase;

/** M11: cash and bank (Document B §4.8). */
class CashTest extends TestCase
{
    use BuildsJournals;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('documents');
    }

    private function safe(): BankAccount
    {
        return BankAccount::query()->where('code', '111002')->firstOrFail();
    }

    #[Test]
    #[Group('UAT-034')]
    #[Group('VR-57')]
    public function the_book_balance_is_derived_from_posted_entries(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(500_000_000));

        $this->assertSame(500_000_000, app(BookBalance::class)->at($this->safe(), CarbonImmutable::parse('2026-12-31')));
        $this->assertSame(0, app(BookBalance::class)->at($this->safe(), CarbonImmutable::parse('2026-03-14')));
        $this->assertFalse(Schema::hasColumn('bank_accounts', 'book_balance'));
        $this->assertFalse(Schema::hasColumn('reconciliations', 'book_balance'));
    }

    #[Test]
    #[Group('UAT-035')]
    #[Group('VR-58')]
    public function a_variance_leaves_the_account_unreconciled_and_raises_an_exception(): void
    {
        $this->postEntry('TT-01', $this->fundingLines(500_000_000));
        $service = app(ReconciliationService::class);

        $reconciliation = $service->prepare(
            $this->maker(), $this->safe(), CarbonImmutable::parse('2026-03-31'), 497_000_000,
            UploadedFile::fake()->create('count.pdf', 20, 'application/pdf'), $this->maker(),
        );

        $service->approve($this->manager(), $reconciliation);

        $this->assertFalse($service->isReconciled($reconciliation->fresh()));
        $exception = ControlException::query()->where('category', ExceptionCategory::CashVariance)->firstOrFail();
        $this->assertSame(-3_000_000, $exception->amount);
    }

    #[Test]
    #[Group('VR-58')]
    public function a_reconciliation_needs_its_evidence_and_a_responsible_accountant(): void
    {
        $this->expectException(RuleViolation::class);

        app(ReconciliationService::class)->prepare($this->maker(), $this->safe(), CarbonImmutable::parse('2026-03-31'), 1, null, null);
    }
}
