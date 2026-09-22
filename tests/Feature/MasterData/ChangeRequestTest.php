<?php

declare(strict_types=1);

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\Account;
use App\Domain\MasterData\ChangeRequestService;
use App\Domain\MasterData\Enums\ChangeRequestStatus;
use App\Domain\Shared\Exceptions\RuleViolation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/** VR-60. A chart change is proposed, approved by someone else, and logged. */
#[Group('VR-60')]
class ChangeRequestTest extends TestCase
{
    use ActsAsRole;

    #[Test]
    #[Group('UAT-050')]
    public function a_proposed_change_is_applied_only_on_approval_and_is_logged(): void
    {
        $senior = $this->userWithRole('senior_accountant');
        $manager = $this->userWithRole('finance_manager');
        $account = Account::query()->where('code', '610005')->firstOrFail();

        $service = app(ChangeRequestService::class);
        $request = $service->propose($senior, 'account', $account, ['name' => 'Telecommunications'], 'Clearer name');

        $this->assertSame('Communications', $account->fresh()->name, 'Nothing changes until approved.');

        $service->approve($manager, $request, 'Agreed', 'FM-2026-01');

        $this->assertSame('Telecommunications', $account->fresh()->name);
        $this->assertSame(ChangeRequestStatus::Applied, $request->fresh()->status);

        $audit = DB::table('audit_logs')->where('table_name', 'accounts')->where('object_id', $account->id)
            ->where('field_name', 'name')->latest('audit_id')->first();

        $this->assertSame('"Communications"', (string) $audit->old_value);
        $this->assertSame('"Telecommunications"', (string) $audit->new_value);
        $this->assertSame('Clearer name', $audit->reason);
    }

    #[Test]
    public function the_proposer_cannot_approve_their_own_change(): void
    {
        $manager = $this->userWithRole('finance_manager');
        $service = app(ChangeRequestService::class);
        $request = $service->propose($manager, 'account', Account::query()->where('code', '610005')->firstOrFail(), ['name' => 'X'], 'why');

        $this->expectException(RuleViolation::class);

        $service->approve($manager, $request);
    }

    #[Test]
    public function an_account_code_cannot_be_changed(): void
    {
        $this->expectException(RuleViolation::class);

        app(ChangeRequestService::class)->propose(
            $this->userWithRole('senior_accountant'),
            'account',
            Account::query()->where('code', '610005')->firstOrFail(),
            ['code' => '610099'],
            'renumber',
        );
    }
}
