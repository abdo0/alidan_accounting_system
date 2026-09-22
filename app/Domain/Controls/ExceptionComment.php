<?php

declare(strict_types=1);

namespace App\Domain\Controls;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A comment on an exception; the Internal Auditor may comment but not change (Document C tab 19). */
class ExceptionComment extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'exception_comments';

    protected $fillable = ['exception_id', 'user_id', 'comment'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
