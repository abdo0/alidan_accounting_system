<?php

declare(strict_types=1);

namespace App\Domain\Shared\Documents;

use App\Domain\Controls\ControlException;
use App\Domain\Controls\Exceptions\ExceptionLifecycle;
use App\Domain\Ledger\Enums\DocStatus;
use App\Domain\Ledger\JournalHeader;
use App\Domain\Shared\Exceptions\RuleViolation;
use App\Models\User;
use App\Support\DatabaseContext;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * The one change a posted entry accepts: its evidence arriving. Document status may
 * only improve (Missing -> Partial -> Complete); the database refuses anything else
 * even inside this controlled correction. When the document is complete, the
 * missing-document exception closes with that as its resolution -- "missing and
 * partial documents remain visible until the document arrives" (Document B §4.7).
 */
final class DocumentEvidenceService
{
    public function __construct(private readonly ExceptionLifecycle $lifecycle) {}

    public function record(User $actor, JournalHeader $journal, DocStatus $status, ?string $docRef): JournalHeader
    {
        if (! $actor->hasPermission('journal.create') && ! $actor->hasPermission('journal.review')) {
            throw new AuthorizationException(__('rules.journal.not_authorised'));
        }

        if ($status->rank() < $journal->doc_status->rank()) {
            throw RuleViolation::because('VR-13', 'rules.documents.status_only_improves');
        }

        if ($status === DocStatus::Complete && trim((string) $docRef) === '') {
            throw RuleViolation::because('VR-14', 'validation_rules.VR-14');
        }

        return DatabaseContext::withAudit('document_evidence', __('rules.documents.evidence_recorded'), function () use ($actor, $journal, $status, $docRef): JournalHeader {
            DatabaseContext::setLocal('app.controlled_correction', '1');

            try {
                $journal->forceFill(['doc_status' => $status, 'doc_ref' => $docRef ?? $journal->doc_ref])->save();
            } finally {
                DatabaseContext::setLocal('app.controlled_correction', '');
            }

            if ($status === DocStatus::Complete) {
                $open = ControlException::query()->where('dedupe_key', 'document:'.$journal->id)->where('status', '!=', 'resolved')->first();

                if ($open !== null) {
                    $this->lifecycle->resolve(null, $open, __('rules.documents.received', ['ref' => (string) $docRef]), $actor->id);
                }
            }

            return $journal;
        });
    }
}
