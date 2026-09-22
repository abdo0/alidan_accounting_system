<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Domain\Ledger\Validation\JournalRejected;
use App\Domain\Ledger\Validation\Violation;
use App\Domain\Shared\Audit\AuditRecorder;
use App\Domain\Shared\Exceptions\RuleViolation;
use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

/**
 * Shows a refusal as the rules that caused it: every finding, each named by its
 * specification code, never a stack trace.
 */
final class RuleViolationPresenter
{
    public static function notify(Throwable $refusal): void
    {
        // Document B §8: every authorisation failure is logged.
        if ($refusal instanceof AuthorizationException) {
            app(AuditRecorder::class)->event('authorization_denied', 'users', auth()->id(), $refusal->getMessage());
        }

        $body = match (true) {
            $refusal instanceof JournalRejected => implode("\n", array_map(fn (Violation $v): string => '• '.$v->describe(), $refusal->violations)),
            $refusal instanceof RuleViolation => $refusal->rule.': '.$refusal->getMessage(),
            default => $refusal->getMessage(),
        };

        Notification::make()
            ->danger()
            ->title(__('pages.journal.refused'))
            ->body($body)
            ->persistent()
            ->send();
    }

    /** @return class-string<Throwable>[] */
    public static function handled(): array
    {
        return [JournalRejected::class, RuleViolation::class, AuthorizationException::class];
    }

    /**
     * Runs an action, presenting a refusal instead of throwing it.
     *
     * @template T
     *
     * @param  callable(): T  $action
     * @return T|null
     */
    public static function attempt(callable $action): mixed
    {
        try {
            return $action();
        } catch (JournalRejected|RuleViolation|AuthorizationException $refusal) {
            self::notify($refusal);

            return null;
        }
    }
}
