<?php

declare(strict_types=1);

namespace App\Domain\Controls\Exceptions;

use App\Domain\Controls\ControlException;
use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\Controls\Enums\ExceptionStatus;
use App\Domain\Ledger\Posting\Sequencer;
use App\Domain\Organisation\Company;
use App\Models\User;

/**
 * Opens an exception, once. The dedupe key makes raising idempotent: the same
 * missing document or suspense line never opens two exceptions, however often the
 * entry is re-checked. Numbers come from a gapless sequence and are never reused.
 */
final class ExceptionRaiser
{
    public function __construct(private readonly Sequencer $sequencer) {}

    /** @param  array<string, mixed>  $attributes */
    public function raise(ExceptionCategory $category, string $subject, array $attributes = [], ?string $dedupeKey = null): ControlException
    {
        if ($dedupeKey !== null) {
            $existing = ControlException::query()->where('dedupe_key', $dedupeKey)->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        $year = (string) now()->year;

        return ControlException::query()->create($attributes + [
            'company_id' => Company::current()->id,
            'exception_no' => $this->sequencer->next('exception', 'year:'.$year, 'EXC-'.$year.'-', 4),
            'category' => $category,
            'raised_date' => now()->toDateString(),
            'subject' => $subject,
            'status' => ExceptionStatus::Open,
            'owner_id' => $attributes['owner_id'] ?? $this->defaultOwner(),
            'raised_by' => auth()->id(),
            'dedupe_key' => $dedupeKey,
        ]);
    }

    /** The Finance Manager owns an exception nobody else has been named for. */
    private function defaultOwner(): ?int
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('name', 'finance_manager'))
            ->orderBy('id')
            ->value('id');
    }
}
