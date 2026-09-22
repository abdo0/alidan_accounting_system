<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A piece of evidence (M16). Content-addressed and immutable.
 *
 * @property int $id
 * @property string $documentable_type
 * @property int $documentable_id
 * @property int|null $journal_line_id
 * @property string $doc_type
 * @property string|null $doc_ref
 * @property string $disk
 * @property string $object_key
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property int $uploaded_by
 */
class Document extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    public const TYPES = [
        'invoice', 'receipt', 'payment_voucher', 'receipt_voucher', 'contract',
        'certificate', 'board_minute', 'bank_statement', 'approval', 'other',
    ];

    protected $table = 'documents';

    protected $fillable = [
        'documentable_type', 'documentable_id', 'journal_line_id', 'doc_type', 'doc_ref', 'doc_date', 'description',
        'disk', 'object_key', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'uploaded_by', 'uploaded_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'doc_date' => 'immutable_date',
            'uploaded_at' => 'immutable_datetime',
        ];
    }

    /** @return MorphTo<Model, $this> */
    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
