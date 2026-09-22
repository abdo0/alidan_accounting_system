<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Filament\Pages\Auth\EditProfile;
use Filament\Facades\Filament;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/** Two-factor enrolment is offered, never required, including for the administrator. */
class RequireMfaForSensitiveRolesTest extends TestCase
{
    use ActsAsRole;

    #[Test]
    public function a_sensitive_role_without_enrolment_can_use_home_and_the_menu(): void
    {
        $this->actingAs($this->userWithRole('system_admin', mfa: false));

        $this->get('/admin')->assertOk();
        $this->get('/admin/profile')->assertOk();
    }

    #[Test]
    public function a_first_visit_shows_the_optional_two_factor_reminder(): void
    {
        $this->actingAs($this->userWithRole('finance_manager', mfa: false));

        $this->get('/admin')
            ->assertOk()
            ->assertSessionHas('mfa_optional_notice_shown', true);
    }

    #[Test]
    public function enrolment_still_records_confirmation(): void
    {
        $user = $this->userWithRole('finance_manager', mfa: false);
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        $this->assertTrue($user->fresh()->hasEnrolledMfa());
        $this->assertNotNull($user->fresh()->mfa_confirmed_at);
    }

    #[Test]
    public function the_panel_profile_page_is_the_custom_enrolment_page(): void
    {
        $this->assertSame(EditProfile::class, Filament::getPanel('admin')->getProfilePage());
    }
}
