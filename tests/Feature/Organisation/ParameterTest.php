<?php

declare(strict_types=1);

namespace Tests\Feature\Organisation;

use App\Domain\Organisation\Enums\ParameterCode;
use App\Domain\Organisation\Parameter;
use App\Domain\Organisation\ParameterResolver;
use App\Domain\Organisation\ParameterService;
use App\Domain\Shared\Exceptions\RuleViolation;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/** M00. Effective-dated parameters, read only through ParameterResolver. */
class ParameterTest extends TestCase
{
    use ActsAsRole;

    #[Test]
    public function the_fifteen_parameters_of_the_specification_are_loaded(): void
    {
        $this->assertSame(15, Parameter::query()->whereNotNull('spec_ref')->count());
        $this->assertSame('0.20', app(ParameterResolver::class)->decimal(ParameterCode::GovShareRate));
        $this->assertSame('112090', app(ParameterResolver::class)->value(ParameterCode::SuspenseAccount));
    }

    #[Test]
    #[Group('VR-39')]
    public function the_commercial_operation_date_is_not_set_and_never_defaulted(): void
    {
        $this->assertNull(app(ParameterResolver::class)->commercialOperationDate());
        $this->assertNull(app(ParameterResolver::class)->value(ParameterCode::RetentionPctDefault));
    }

    #[Test]
    #[Group('UAT-033')]
    public function a_new_rate_applies_only_from_its_effective_date(): void
    {
        $manager = $this->userWithRole('finance_manager');
        $from = CarbonImmutable::parse('2027-01-01');

        app(ParameterService::class)->change($manager, ParameterCode::GovShareRate, '0.25', $from, 'BOARD-2026-14', 'Contract amendment');

        $resolver = app(ParameterResolver::class);
        $this->assertSame('0.20', $resolver->decimal(ParameterCode::GovShareRate, CarbonImmutable::parse('2026-12-31')));
        $this->assertSame('0.25', $resolver->decimal(ParameterCode::GovShareRate, $from));

        $this->assertSame(2, Parameter::query()->where('param_code', 'gov_share_rate')->count(), 'The old value stays in the history.');
        $this->assertTrue(
            DB::table('audit_log')->where('table_name', 'parameters')->where('action', 'high_risk_param_change')->exists(),
            'A change to the share rate is a high-risk audit event.',
        );
    }

    #[Test]
    public function only_the_finance_manager_may_change_a_parameter(): void
    {
        $this->expectException(AuthorizationException::class);

        app(ParameterService::class)->change(
            $this->userWithRole('senior_accountant'),
            ParameterCode::GovShareRate,
            '0.25',
            CarbonImmutable::parse('2027-01-01'),
            'BOARD-1',
            'reason',
        );
    }

    #[Test]
    public function a_change_needs_a_reason_and_an_approval_reference(): void
    {
        $this->expectException(RuleViolation::class);

        app(ParameterService::class)->change(
            $this->userWithRole('finance_manager'),
            ParameterCode::CommercialOperationDate,
            '2027-06-01',
            CarbonImmutable::parse('2027-01-01'),
            '',
            '',
        );
    }

    #[Test]
    public function history_cannot_be_rewritten_in_place(): void
    {
        $this->expectException(QueryException::class);

        Parameter::query()->where('param_code', 'gov_share_rate')->update(['value' => '0.30']);
    }
}
