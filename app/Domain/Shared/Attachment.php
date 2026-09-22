<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Evidence for a transaction. Content-addressed and immutable: the database refuses
 * updates and deletes outright, so this class makes that explicit rather than letting
 * a write appear to succeed.
 *
 * @property int $id
 * @property string $object_key
 * @property string $sha256
 * @property string $original_name
 */
class Attachment extends Model
{
    public const UPDATED_AT = null;

    public const CREATED_AT = 'uploaded_at';

    protected $fillable = [
        'attachable_type', 'attachable_id', 'disk', 'object_key', 'original_name',
        'mime_type', 'size_bytes', 'sha256', 'document_type', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['uploaded_at' => 'immutable_datetime', 'size_bytes' => 'integer'];
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function delete(): ?bool
    {
        throw new RuntimeException('Evidence cannot be withdrawn once attached.');
    }
}
