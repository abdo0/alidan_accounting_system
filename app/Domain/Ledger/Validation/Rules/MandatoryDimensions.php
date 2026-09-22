<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Validation\Rules;

use App\Domain\Ledger\Enums\CapexOpex;
use App\Domain\Ledger\JournalLine;
use App\Domain\Ledger\Rules\MandatoryDimension;
use App\Domain\Ledger\Validation\PostingContext;
use App\Domain\Ledger\Validation\RuleGroup;
use App\Domain\MasterData\Account;
use App\Domain\MasterData\Enums\AccountType;
use App\Domain\MasterData\Project;

/**
 * PR-DIM: the mandatory dimensions of the resolved posting rule (Document C tab 13).
 * Each dimension is demanded where it belongs: on every line, on the lines of the
 * accounts it describes, on at least one line, or on the header.
 */
final class MandatoryDimensions extends BaseRule
{
    public function code(): string
    {
        return 'PR-DIM';
    }

    public function group(): RuleGroup
    {
        return RuleGroup::PostingRule;
    }

    public function check(PostingContext $context): array
    {
        $rule = $context->postingRule;

        if ($rule === null) {
            return [];
        }

        $violations = [];

        foreach ($rule->mandatory_dimensions as $token) {
            $dimension = MandatoryDimension::fromToken($token);

            if ($dimension->isCoveredElsewhere()) {
                continue;
            }

            foreach ($this->missing($dimension, $context) as $line) {
                $violations[] = $this->violation(['dimension' => __('dimensions.'.$dimension->name), 'rule' => $rule->code], $line);
            }
        }

        return $violations;
    }

    /**
     * The lines lacking the dimension, or [null] when a header value or an
     * at-least-one-line dimension is missing.
     *
     * @return list<JournalLine|null>
     */
    private function missing(MandatoryDimension $dimension, PostingContext $context): array
    {
        $lines = $context->lines();

        $everyLine = fn (callable $has, ?callable $applies = null): array => $lines
            ->filter(fn (JournalLine $line): bool => $applies === null || $applies($line->account))
            ->reject(fn (JournalLine $line): bool => $has($line))
            ->values()
            ->all();

        $anyLine = fn (callable $has): array => $lines->contains($has) ? [] : [null];
        $header = fn (?string $value): array => trim((string) $value) === '' ? [null] : [];

        return match ($dimension) {
            MandatoryDimension::Project => $everyLine(fn (JournalLine $l): bool => $l->project_id > 0),
            MandatoryDimension::CostCenter => $everyLine(fn (JournalLine $l): bool => $l->cost_center_id !== null || $l->vr05_exemption_ref !== null),
            MandatoryDimension::RespCenter => $everyLine(fn (JournalLine $l): bool => $l->resp_center_id !== null),
            MandatoryDimension::AdvanceHolder => $everyLine(
                fn (JournalLine $l): bool => $l->advance_holder_id !== null,
                fn (Account $a): bool => $a->is_advance_account,
            ),
            MandatoryDimension::Counterparty => $anyLine(fn (JournalLine $l): bool => $l->counterparty_id !== null),
            MandatoryDimension::Shareholder => $anyLine(fn (JournalLine $l): bool => $l->counterparty?->is_shareholder === true),
            MandatoryDimension::FundingSource => $anyLine(fn (JournalLine $l): bool => $l->funding_source_id !== null),
            MandatoryDimension::FundingBatch => $anyLine(fn (JournalLine $l): bool => $l->funding_batch_id !== null),
            MandatoryDimension::Contract => $anyLine(fn (JournalLine $l): bool => $l->contract_id !== null),
            MandatoryDimension::WorkPackage => $anyLine(fn (JournalLine $l): bool => $l->work_package_id !== null),
            MandatoryDimension::CapexOpex => $everyLine(fn (JournalLine $l): bool => $l->capex_opex !== null, self::isCostLine(...)),
            MandatoryDimension::CapexOpexCapex => $everyLine(fn (JournalLine $l): bool => $l->capex_opex === CapexOpex::Capex, self::isCostLine(...)),
            MandatoryDimension::CapexOpexOpex => $everyLine(fn (JournalLine $l): bool => $l->capex_opex === CapexOpex::Opex, self::isCostLine(...)),
            MandatoryDimension::AssetClass => $everyLine(fn (JournalLine $l): bool => $l->asset_class !== null, self::isAssetLine(...)),
            MandatoryDimension::HandoverReq => $everyLine(fn (JournalLine $l): bool => $l->handover_req !== null, self::isHandoverLine(...)),
            MandatoryDimension::ProjectPrj02 => $everyLine(fn (JournalLine $l): bool => $l->project_id === Project::query()->where('code', 'PRJ-02')->value('id')),
            MandatoryDimension::SettlementDeadline => $everyLine(
                fn (JournalLine $l): bool => $l->settlement_deadline !== null,
                fn (Account $a): bool => $a->is_advance_account,
            ),
            MandatoryDimension::ApprovalRef => $header($context->header->approval_ref),
            MandatoryDimension::DocumentRef => $header($context->header->doc_ref),
            MandatoryDimension::ResolutionRef => $header($context->header->resolution_ref),
            MandatoryDimension::LinkedJournal => $context->header->linked_journal_id === null ? [null] : [],
            default => [],
        };
    }

    /** The CAPEX / OPEX classification describes cost and asset lines. */
    private static function isCostLine(Account $account): bool
    {
        return $account->account_type === AccountType::Expense
            || in_array($account->fs_line_code, [self::CIP_LINE, self::FIXED_ASSET_LINE, 'SFP-A-050'], true);
    }

    /** Asset class is required on asset lines (Document C tab 11). */
    private static function isAssetLine(Account $account): bool
    {
        return in_array($account->fs_line_code, [self::CIP_LINE, self::FIXED_ASSET_LINE, 'SFP-A-050', 'SFP-A-060'], true);
    }

    /** Handover requirement is required on 115xxx and 118xxx lines (Document C tab 11). */
    private static function isHandoverLine(Account $account): bool
    {
        return in_array($account->fs_line_code, [self::CIP_LINE, self::FIXED_ASSET_LINE], true);
    }
}
