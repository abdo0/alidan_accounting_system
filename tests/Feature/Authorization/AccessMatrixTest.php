<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domain\Access\Enums\PermissionScope;
use App\Domain\Access\Role;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\Project;
use App\Domain\Organisation\Parameter;
use App\Models\User;
use App\Providers\AuthServiceProvider;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * M17. The eight roles of Document C tab 19 and the separation of duties the
 * matrix is built to guarantee.
 */
class AccessMatrixTest extends TestCase
{
    use ActsAsRole;

    /** Everything that changes the books, the controls or the configuration. */
    private const ACCOUNTING_AUTHORITY = [
        'journal.create', 'journal.edit_draft', 'journal.submit', 'journal.review', 'journal.approve',
        'journal.post', 'journal.reverse', 'period.soft_close', 'period.final_close', 'period.lock',
        'period.reopen', 'masterdata.modify', 'coa.propose', 'coa.approve', 'parameters.manage',
        'cash.reconcile_prepare', 'cash.reconcile_review', 'cash.reconcile_approve',
        'exceptions.resolve', 'duplicates.disposition', 'migration.run',
    ];

    #[Test]
    public function the_eight_roles_of_the_matrix_exist(): void
    {
        $this->assertSame(
            ['ROLE-01', 'ROLE-02', 'ROLE-03', 'ROLE-04', 'ROLE-05', 'ROLE-06', 'ROLE-07', 'ROLE-08'],
            Role::query()->orderBy('code')->pluck('code')->all(),
        );
    }

    #[Test]
    public function the_system_administrator_holds_no_accounting_authority(): void
    {
        $admin = $this->userWithRole('system_admin');

        foreach (self::ACCOUNTING_AUTHORITY as $permission) {
            $this->assertFalse($admin->hasPermission($permission), "The System Administrator holds {$permission}.");
        }

        $this->assertTrue($admin->hasPermission('users.manage'));
    }

    #[Test]
    public function no_role_holds_create_approve_post_and_administer_together(): void
    {
        foreach (Role::query()->with('permissions')->get() as $role) {
            $names = $role->permissions->pluck('name');

            $this->assertFalse(
                $names->contains('journal.create') && $names->contains('journal.approve')
                    && $names->contains('journal.post') && $names->contains('users.manage'),
                "{$role->code} combines create, approve, post and administer.",
            );
        }
    }

    #[Test]
    public function only_the_finance_manager_approves_and_posts(): void
    {
        foreach (['journal.approve', 'journal.post', 'journal.reverse', 'period.reopen', 'parameters.manage'] as $permission) {
            $holders = Role::query()
                ->whereHas('permissions', fn ($q) => $q->where('name', $permission))
                ->pluck('code')
                ->all();

            $this->assertSame(['ROLE-05'], $holders, "{$permission} is held by ".implode(', ', $holders));
        }
    }

    #[Test]
    public function the_senior_accountant_reviews_and_soft_closes_but_does_not_approve(): void
    {
        $senior = $this->userWithRole('senior_accountant');

        $this->assertTrue($senior->hasPermission('journal.review'));
        $this->assertTrue($senior->hasPermission('period.soft_close'));
        $this->assertFalse($senior->hasPermission('period.final_close'));
        $this->assertFalse($senior->hasPermission('journal.approve'));
        $this->assertTrue($senior->hasPermission('coa.propose'));
        $this->assertFalse($senior->hasPermission('coa.approve'));
    }

    #[Test]
    public function data_entry_sees_and_edits_only_its_own_work(): void
    {
        $clerk = $this->userWithRole('data_entry');

        $this->assertSame(PermissionScope::Own, $clerk->permissionScope('journal.view'));
        $this->assertSame(PermissionScope::Own, $clerk->permissionScope('journal.edit_draft'));
        $this->assertTrue($clerk->hasPermissionOver('journal.edit_draft', $clerk->id));
        $this->assertFalse($clerk->hasPermissionOver('journal.edit_draft', $clerk->id + 1));
        $this->assertTrue($clerk->hasPermission('journal.submit'));
    }

    /** @return array<string, array{string}> */
    public static function readOnlyRoles(): array
    {
        return ['management' => ['management_viewer'], 'internal auditor' => ['internal_auditor']];
    }

    #[Test]
    #[Group('UAT-049')]
    #[DataProvider('readOnlyRoles')]
    public function a_read_only_role_cannot_change_anything(string $role): void
    {
        $user = $this->userWithRole($role);

        $this->assertTrue($user->isReadOnly());
        $this->assertFalse(Gate::forUser($user)->allows('create', Project::class));
        $this->assertFalse(Gate::forUser($user)->allows('change', Parameter::class));
        $this->assertFalse($user->hasPermission('journal.create'));
        $this->assertFalse($user->hasPermission('journal.post'));
    }

    #[Test]
    public function the_internal_auditor_reads_the_audit_trail_and_may_comment(): void
    {
        $auditor = $this->userWithRole('internal_auditor');

        $this->assertTrue($auditor->hasPermission('audit.view'));
        $this->assertTrue($auditor->hasPermission('exceptions.comment'));
        $this->assertFalse($auditor->hasPermission('exceptions.resolve'));
    }

    #[Test]
    public function the_chart_is_never_edited_or_deleted_through_a_form(): void
    {
        $manager = $this->userWithRole('finance_manager');
        $account = Account::query()->where('code', '111002')->firstOrFail();

        $this->assertFalse(Gate::forUser($manager)->allows('create', Account::class));
        $this->assertFalse(Gate::forUser($manager)->allows('update', $account));
        $this->assertFalse(Gate::forUser($manager)->allows('delete', $account));
    }

    #[Test]
    public function a_deactivated_user_can_do_nothing_at_all(): void
    {
        $user = $this->userWithRole('finance_manager', active: false);

        $this->assertFalse($user->hasPermission('journal.post'));
        $this->assertFalse(Gate::forUser($user)->allows('viewAny', Project::class));
    }

    #[Test]
    public function every_registered_model_actually_resolves_a_policy(): void
    {
        foreach (array_keys(AuthServiceProvider::POLICIES) as $model) {
            $this->assertNotNull(Gate::getPolicyFor($model), "{$model} has no policy; every gate on it would pass.");
        }
    }

    #[Test]
    public function a_user_with_no_role_is_not_admitted_to_the_panel(): void
    {
        $this->actingAs($this->userWithoutRoles());

        $this->get('/admin')->assertForbidden();
    }

    #[Test]
    public function sensitive_abilities_do_not_require_the_second_factor(): void
    {
        $withoutMfa = $this->userWithRole('finance_manager', mfa: false);
        $withMfa = $this->userWithRole('finance_manager');

        $this->assertTrue(Gate::forUser($withoutMfa)->allows('change', Parameter::class));
        $this->assertTrue(Gate::forUser($withMfa)->allows('change', Parameter::class));
        $this->assertInstanceOf(User::class, $withMfa);
    }
}
