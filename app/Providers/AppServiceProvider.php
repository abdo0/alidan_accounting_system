<?php

declare(strict_types=1);

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Lazy-loading and silently-discarded attributes are both ways for a ledger
        // to be quietly wrong, so they are hard errors here.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Financial records are never destroyed by a stray mass-assignment.
        Model::preventSilentlyDiscardingAttributes();

        Date::use(CarbonImmutable::class);
    }
}
