<?php

declare(strict_types=1);

namespace App\Domain\Shared\Audit;

use Illuminate\Support\Facades\DB;

/**
 * Audit rows the application writes itself (operation 'A'): events no table
 * trigger sees -- a login, a refused posting attempt, an export, an imported
 * reclassification. Document B §2.2: "if any rule fails, nothing is written except
 * a rejected-attempt audit row".
 */
final class AuditRecorder
{
    /** @param  array<string, mixed>|null  $payload */
    public function event(string $action, string $objectType, ?int $objectId, ?string $reason = null, ?array $payload = null, ?int $userId = null): void
    {
        DB::table('audit_log')->insert([
            'table_name' => $objectType,
            'object_type' => $objectType,
            'record_id' => $objectId,
            'operation' => 'A',
            'action' => $action,
            'new_values' => $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE),
            'actor_user_id' => $userId ?? auth()->id(),
            'actor_ip' => request()->ip(),
            'reason' => $reason,
        ]);
    }
}
