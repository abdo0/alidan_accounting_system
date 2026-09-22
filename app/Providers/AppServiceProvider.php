<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Access\Listeners\AuthenticationAudit;
use App\Domain\Cash\Reconciliation;
use App\Domain\Closing\Gates\CloseGate;
use App\Domain\Closing\PeriodCloseService;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Ledger\Posting\PostingGuard;
use App\Domain\Ledger\Posting\PostingObserver;
use App\Domain\Ledger\Posting\PostingService;
use App\Domain\Ledger\Validation\ValidationChain;
use App\Domain\Ledger\Validation\ValidationRule;
use App\Domain\Shared\Documents\NullVirusScanner;
use App\Domain\Shared\Documents\VirusScanner;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ValidationChain::class, fn (Application $app): ValidationChain => new ValidationChain(
            array_map(fn (string $rule): ValidationRule => $app->make($rule), config('shh.validation_rules', [])),
        ));

        $this->app->bind(VirusScanner::class, NullVirusScanner::class);

        $this->app->when(PeriodCloseService::class)
            ->needs('$gates')
            ->give(fn (Application $app): array => array_map(fn (string $class): CloseGate => $app->make($class), config('shh.close_gates', [])));

        $this->app->when(PostingService::class)
            ->needs('$guards')
            ->give(fn (Application $app): array => array_map(fn (string $class): PostingGuard => $app->make($class), config('shh.posting_guards', [])));

        $this->app->when(PostingService::class)
            ->needs('$observers')
            ->give(fn (Application $app): array => array_map(fn (string $class): PostingObserver => $app->make($class), config('shh.posting_observers', [])));
    }

    public function boot(): void
    {
        // Lazy-loading and silently-discarded attributes are both ways for a ledger
        // to be quietly wrong, so they are hard errors here.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Financial records are never destroyed by a stray mass-assignment.
        Model::preventSilentlyDiscardingAttributes();

        Date::use(CarbonImmutable::class);

        Event::subscribe(AuthenticationAudit::class);

        // Polymorphic columns store a stable alias, never a PHP class name.
        Relation::enforceMorphMap([
            'journal_headers' => JournalHeader::class,
            'reconciliations' => Reconciliation::class,
            'users' => User::class,
        ]);
    }
}
