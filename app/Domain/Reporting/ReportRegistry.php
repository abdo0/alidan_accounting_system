<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Reporting\Contracts\Report;
use InvalidArgumentException;

/** Resolves a report code to its class (config/shh.php 'reports'). */
final class ReportRegistry
{
    /** @return array<string, class-string<Report>> */
    public function all(): array
    {
        return config('shh.reports', []);
    }

    public function has(string $code): bool
    {
        return isset($this->all()[$code]);
    }

    public function get(string $code): Report
    {
        $class = $this->all()[$code] ?? throw new InvalidArgumentException("Report {$code} is not available.");

        return app($class);
    }
}
