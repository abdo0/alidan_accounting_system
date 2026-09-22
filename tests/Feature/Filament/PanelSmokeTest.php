<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\GovernmentShare;
use App\Filament\Pages\Reporting\ReportsIndex;
use Filament\Facades\Filament;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\ActsAsRole;
use Tests\TestCase;

/**
 * Every registered resource must actually open.
 *
 * Resources are auto-discovered, so a class that throws on render -- a missing
 * translation key, a relationship that does not exist, an icon clash between a
 * navigation group and its items -- would otherwise only be found by a person
 * clicking on it.
 */
class PanelSmokeTest extends TestCase
{
    use ActsAsRole;

    #[Test]
    public function the_dashboard_opens(): void
    {
        $this->actingAs($this->userWithRole('finance_manager'));

        $this->get('/admin')->assertSuccessful();
    }

    #[Test]
    public function every_resource_index_opens_for_the_finance_manager(): void
    {
        $user = $this->userWithRole('finance_manager');
        $this->actingAs($user);

        $resources = Filament::getPanel('admin')->getResources();

        $this->assertNotEmpty($resources, 'No resources are registered with the panel.');

        $checked = 0;

        foreach ($resources as $resource) {
            if (! $resource::canViewAny()) {
                continue;
            }

            $this->withoutExceptionHandling()
                ->get($resource::getUrl('index'))
                ->assertSuccessful();

            $checked++;
        }

        // Guards against the assertion above being vacuous if canViewAny() ever
        // starts refusing everything.
        $this->assertGreaterThanOrEqual(10, $checked, 'Too few resources were reachable.');
    }

    #[Test]
    public function every_custom_page_opens_for_the_finance_manager(): void
    {
        $this->actingAs($this->userWithRole('finance_manager'));

        foreach ([GovernmentShare::class, ReportsIndex::class] as $page) {
            $this->get($page::getUrl())->assertSuccessful();
        }
    }

    #[Test]
    public function every_resource_names_itself_in_both_languages(): void
    {
        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            foreach (['ar', 'en'] as $locale) {
                $this->app->setLocale($locale);

                foreach (['getModelLabel', 'getPluralModelLabel', 'getNavigationLabel'] as $method) {
                    $label = $resource::{$method}();

                    $this->assertStringNotContainsString(
                        'resources.',
                        $label,
                        "{$resource}::{$method}() has no {$locale} translation.",
                    );
                }
            }
        }
    }

    #[Test]
    public function the_auditor_sees_the_panel_without_a_single_create_button(): void
    {
        $auditor = $this->userWithRole('internal_auditor');
        $this->actingAs($auditor);

        $this->get('/admin')->assertSuccessful();

        foreach (Filament::getPanel('admin')->getResources() as $resource) {
            $this->assertFalse(
                $resource::canCreate(),
                "{$resource} offers the auditor a create button.",
            );
        }
    }

    #[Test]
    public function a_user_without_roles_cannot_reach_the_panel_at_all(): void
    {
        $this->actingAs($this->userWithoutRoles());

        // canAccessPanel() refuses outright rather than redirecting: they are
        // authenticated, so there is nothing to log in as.
        $this->get('/admin')->assertForbidden();
    }
}
