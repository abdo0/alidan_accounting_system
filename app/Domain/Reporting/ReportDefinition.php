<?php

declare(strict_types=1);

namespace App\Domain\Reporting;

use App\Domain\Shared\Concerns\HasTranslatableName;
use App\Domain\Shared\Contracts\HasDisplayName;
use Illuminate\Database\Eloquent\Model;

/**
 * One report of the catalogue (Document C tab 16).
 *
 * @property string $code
 * @property string $name
 * @property string|null $name_ar
 * @property string|null $content
 * @property string $phase
 */
class ReportDefinition extends Model implements HasDisplayName
{
    use HasTranslatableName;

    protected $table = 'report_definitions';

    protected $fillable = ['code', 'name', 'name_ar', 'module', 'content', 'filters', 'source_entities', 'export', 'phase'];
}
