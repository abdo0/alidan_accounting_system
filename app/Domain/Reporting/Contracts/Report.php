<?php

declare(strict_types=1);

namespace App\Domain\Reporting\Contracts;

use App\Domain\Reporting\Filters\FilterSet;
use App\Domain\Reporting\Result\ReportResult;
use App\Models\User;

/** One report of the catalogue (Document C tab 16). */
interface Report
{
    /** RPT-04 */
    public function code(): string;

    /**
     * The filter keys this report accepts (FilterSet::CODES), which the viewer
     * turns into its filter form.
     *
     * @return list<string>
     */
    public function filters(): array;

    /** The permission needed to run it. */
    public function permission(): string;

    public function run(FilterSet $filters, User $user): ReportResult;
}
