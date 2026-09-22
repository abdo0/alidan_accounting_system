<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Ledger\Enums\ApprovalAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One workflow transition or board decision. Permanent: the table refuses UPDATE
 * and DELETE.
 *
 * @property int $id
 * @property string $object_type
 * @property int $object_id
 * @property ApprovalAction $action
 * @property string|null $comment
 * @property string|null $approval_ref
 * @property int|null $user_id
 */
class Approval extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $table = 'approvals';

    protected $fillable = [
        'object_type', 'object_id', 'action', 'from_status', 'to_status', 'comment', 'approval_ref', 'user_id', 'action_at',
    ];

    protected function casts(): array
    {
        return [
            'action' => ApprovalAction::class,
            'action_at' => 'immutable_datetime',
        ];
    }

    public static function record(Model $object, ApprovalAction $action, ?User $user, ?string $from, ?string $to, ?string $comment = null, ?string $approvalRef = null): self
    {
        return self::query()->create([
            'object_type' => $object->getTable(),
            'object_id' => $object->getKey(),
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'comment' => $comment,
            'approval_ref' => $approvalRef,
            'user_id' => $user?->id,
            'action_at' => now(),
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
