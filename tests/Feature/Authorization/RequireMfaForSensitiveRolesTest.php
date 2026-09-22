<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Filament\Pages\Auth\EditProfile;
use Filament\Facades\Filament;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/** A posting or approving role cannot use the panel until two-factor is enrolled. */
class RequireMfaForSensitiveRolesTest extends TestCase
{
    use ActsAsRole;

    #[Test]
    public function a_sensitive_role_without_enrolment_is_sent_to_the_profile_from_home_or_the_menu(): void
    {
        $this->actingAs($this->userWithRole('finance_manager', mfa: false));

        $this->get('/admin')
            ->assertRedirect('/admin/profile');
    }

    #[Test]
    public function the_profile_stays_open_so_enrolment_can_happen(): void
    {
        $this->actingAs($this->userWithRole('finance_manager', mfa: false));

        $this->get('/admin/profile')->assertOk();
    }

    #[Test]
    public function after_the_authenticator_is_enrolled_home_is_reachable_again(): void
    {
        $user = $this->userWithRole('finance_manager', mfa: false);
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        $this->actingAs($user->fresh());

        $this->get('/admin')->assertOk();
        $this->assertNotNull($user->fresh()->mfa_confirmed_at);
    }

    #[Test]
    public function a_role_that_does_not_require_two_factor_is_not_held_on_the_profile(): void
    {
        $this->actingAs($this->userWithRole('accountant', mfa: false));

        $this->get('/admin')->assertOk();
    }

    #[Test]
    public function the_panel_profile_page_is_the_custom_enrolment_page(): void
    {
        $this->assertSame(EditProfile::class, Filament::getPanel('admin')->getProfilePage());
    }
}
