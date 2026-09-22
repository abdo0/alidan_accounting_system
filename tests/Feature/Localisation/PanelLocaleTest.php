<?php

declare(strict_types=1);

namespace Tests\Feature\Localisation;

use App\Domain\Access\Role;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PanelLocaleTest extends TestCase
{
    #[Test]
    public function the_login_page_renders_left_to_right_in_english(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertSee('dir="ltr"', false);
    }

    #[Test]
    public function the_login_page_flips_to_rtl_in_arabic(): void
    {
        $this->withSession(['locale' => 'ar'])
            ->get('/admin/login')
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }

    #[Test]
    public function filament_ships_arabic_interface_strings(): void
    {
        app()->setLocale('ar');

        // Proves we are not relying on untranslated English in the Arabic UI.
        $this->assertSame('rtl', __('filament-panels::layout.direction'));
        $this->assertNotSame(
            'filament-panels::auth/pages/login.title',
            __('filament-panels::auth/pages/login.title'),
        );
    }

    #[Test]
    public function the_locale_switch_persists_to_the_user(): void
    {
        $this->seed(AccessControlSeeder::class);

        $user = User::factory()->create(['locale' => 'en']);
        $user->roles()->attach(Role::where('name', 'gl_accountant')->value('id'));

        $this->actingAs($user)
            ->from('/admin')
            ->get(route('locale.switch', ['locale' => 'ar']))
            ->assertRedirect('/admin');

        $this->assertSame('ar', $user->fresh()->locale);
        $this->assertSame('ar', session('locale'));
    }

    #[Test]
    public function an_unsupported_locale_is_ignored(): void
    {
        $this->from('/admin')->get(route('locale.switch', ['locale' => 'fr']));

        $this->assertNull(session('locale'));
    }

    #[Test]
    public function a_deactivated_user_cannot_reach_the_panel(): void
    {
        $this->seed(AccessControlSeeder::class);

        $user = User::factory()->create(['is_active' => false]);
        $user->roles()->attach(Role::where('name', 'gl_accountant')->value('id'));

        $this->assertFalse($user->canAccessPanel(filament()->getPanel('admin')));
    }

    #[Test]
    public function a_service_account_cannot_reach_the_panel(): void
    {
        $this->seed(AccessControlSeeder::class);

        $user = User::factory()->create(['is_service_account' => true]);
        $user->roles()->attach(Role::where('name', 'gl_accountant')->value('id'));

        $this->assertFalse($user->canAccessPanel(filament()->getPanel('admin')));
    }

    #[Test]
    public function a_user_with_no_role_cannot_reach_the_panel(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->canAccessPanel(filament()->getPanel('admin')));
    }
}
