<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;

/** VR-05: Project and cost centre on every line. */
final class Vr05ProjectCostCentre extends BaseRule
{
    public function code(): string
    {
        return 'VR-05';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::Dimension;
    }

    public function check(PostingContext $context): array
    {
        $violations = [];
        foreach ($context->lines() as $line) {
            $exempt = $context->header->is_migration && $line->vr05_exemption_ref !== null;

            if ($line->project_id < 1 || ($line->cost_center_id === null && ! $exempt)) {
                $violations[] = $this->violation([], $line);
            }
        }

        return $violations;
    }
}
