<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Advances\OverdueAdvances;
use App\Support\DatabaseContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shh:advances:flag-overdue')]
#[Description('Raise an exception for every advance past its settlement deadline with a balance (RE-13)')]
class FlagOverdueAdvances extends Command
{
    public function handle(OverdueAdvances $overdue): int
    {
        DatabaseContext::actingAs(null, 'Scheduled overdue-advance check');

        $this->info(sprintf('%d overdue advance(s).', $overdue->flag()));

        return self::SUCCESS;
    }
}
