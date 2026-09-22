<?php

declare(strict_types=1);

namespace App\Domain\Advances;

use App\Domain\Advances\Enums\AdvanceStatus;
use App\Domain\Controls\ControlException;
use App\Domain\Controls\Enums\ExceptionCategory;
use App\Domain\Controls\Exceptions\ExceptionRaiser;

/**
 * An advance past its settlement deadline with a balance is Overdue and raises an
 * exception (Document B §4.3, RE-13). Run daily by shh:advances:flag-overdue and
 * whenever a posting touches the advance.
 */
final class OverdueAdvances
{
    public function __construct(private readonly ExceptionRaiser $exceptions) {}

    /** @return int the number of overdue advances found */
    public function flag(): int
    {
        $count = 0;

        foreach (Advance::query()->whereNotNull('settlement_deadline')->where('settlement_deadline', '<', now()->toDateString())->get() as $advance) {
            $position = AdvancePosition::of($advance);

            if ($position->status() === AdvanceStatus::Overdue) {
                self::raise($this->exceptions, $position);
                $count++;
            }
        }

        return $count;
    }

    public static function raise(ExceptionRaiser $exceptions, AdvancePosition $position): ControlException
    {
        $advance = $position->advance;

        return $exceptions->raise(
            ExceptionCategory::OverdueAdvance,
            __('controls.exception.overdue_advance', [
                'ref' => $advance->advance_ref,
                'deadline' => $advance->settlement_deadline?->toDateString(),
            ]),
            [
                'journal_header_id' => $advance->issue_journal_id,
                'account_id' => $advance->account_id,
                'counterparty_id' => $advance->holder_id,
                'amount' => $position->outstanding,
            ],
            'overdue:'.$advance->id,
        );
    }
}
