<?php

declare(strict_types=1);

namespace App\Domain\MasterData;

use App\Domain\MasterData\Enums\ChangeRequestAction;
use App\Domain\MasterData\Enums\ChangeRequestStatus;
use App\Domain\Shared\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A proposed change to controlled master data (VR-60, RE-11).
 *
 * @property int $id
 * @property string $object_type
 * @property int|null $object_id
 * @property ChangeRequestAction $action
 * @property array<string, mixed> $payload
 * @property array<string, mixed>|null $previous
 * @property string $reason
 * @property string|null $approval_ref
 * @property ChangeRequestStatus $status
 * @property int|null $proposed_by
 * @property int|null $decided_by
 */
class ChangeRequest extends Model
{
    use BelongsToCompany;

    protected $table = 'change_requests';

    protected $fillable = [
        'company_id', 'object_type', 'object_id', 'action', 'payload', 'previous', 'reason',
        'approval_ref', 'status', 'proposed_by', 'proposed_at', 'decided_by', 'decided_at',
        'decision_note', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => ChangeRequestAction::class,
            'status' => ChangeRequestStatus::class,
            'payload' => 'array',
            'previous' => 'array',
            'proposed_at' => 'immutable_datetime',
            'decided_at' => 'immutable_datetime',
            'applied_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
