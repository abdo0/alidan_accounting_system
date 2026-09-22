<?php

declare(strict_types=1);

namespace App\Domain\Shared\Concerns;

use App\Domain\Organisation\Company;
use Illuminate\Database\Eloquent\Model;

/**
 * Stamps new records with the reporting entity. There is one company at go-live,
 * so forms do not ask for it; the column exists so consolidation can be added
 * without restructuring.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::creating(function (Model $model): void {
            if ($model->getAttribute('company_id') === null) {
                $model->setAttribute('company_id', Company::current()->id);
            }
        });
    }
}
