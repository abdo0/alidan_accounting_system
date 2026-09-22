<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Domain\Access\Role;
use App\Models\User;
use App\Support\DatabaseContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * M10. Every change is recorded by trigger, attributed, and never removable
 * (Document B §3.4 and §7, acceptance criterion 8).
 */
class AuditTrailTest extends TestCase
{
    private int $baseline;

    protected function setUp(): void
    {
        parent::setUp();

        $this->baseline = (int) DB::table('audit_log')->max('id');
    }

    /** @return Collection<int, \stdClass> */
    private function newRows(string $table): Collection
    {
        return DB::table('audit_log')
            ->where('id', '>', $this->baseline)
            ->where('table_name', $table)
            ->orderBy('id')
            ->get();
    }

    private function probeRole(string $name, string $label = 'Probe'): Role
    {
        return Role::create([
            'name' => $name, 'label_en' => $label, 'is_read_only' => false,
            'is_system' => false, 'requires_mfa' => false,
        ]);
    }

    #[Test]
    public function it_records_inserts_updates_and_deletes(): void
    {
        $role = $this->probeRole('probe');
        $role->update(['label_en' => 'Probe Renamed']);
        $role->delete();

        $this->assertSame(['I', 'U', 'D'], $this->newRows('roles')->pluck('operation')->all());
    }

    #[Test]
    #[Group('UAT-050')]
    public function it_records_which_columns_changed_with_old_and_new_values(): void
    {
        $role = $this->probeRole('probe2', 'Before');
        $role->update(['label_en' => 'After', 'requires_mfa' => true]);

        $update = $this->newRows('roles')->firstWhere('operation', 'U');
        $this->assertNotNull($update);

        $changed = explode(',', trim((string) $update->changed_columns, '{}'));

        $this->assertContains('label_en', $changed);
        $this->assertContains('requires_mfa', $changed);
        $this->assertSame('Before', json_decode((string) $update->old_values, true)['label_en']);
        $this->assertSame('After', json_decode((string) $update->new_values, true)['label_en']);

        $fields = DB::table('audit_logs')->where('audit_id', $update->id)->pluck('field_name')->all();
        $this->assertContains('label_en', $fields, 'The field-level view carries one row per changed field.');
    }

    #[Test]
    public function it_attributes_the_change_to_the_acting_user_and_action(): void
    {
        DatabaseContext::withAudit('probe_action', 'unit test', function (): void {
            DatabaseContext::setLocal('app.user_id', '4242');
            $this->probeRole('probe3');
        });

        $entry = $this->newRows('roles')->first();

        $this->assertNotNull($entry);
        $this->assertSame(4242, (int) $entry->actor_user_id);
        $this->assertSame('unit test', $entry->reason);
        $this->assertSame('probe_action', $entry->action);
    }

    #[Test]
    public function a_no_op_update_is_not_recorded(): void
    {
        $role = $this->probeRole('probe4', 'Same');
        $before = $this->newRows('roles')->count();

        DB::table('roles')->where('id', $role->id)->update(['label_en' => 'Same']);

        $this->assertSame($before, $this->newRows('roles')->count());
    }

    #[Test]
    public function the_audit_log_cannot_be_updated(): void
    {
        $this->probeRole('probe5');
        $id = $this->newRows('roles')->first()->id;

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('append-only');

        DB::table('audit_log')->where('id', $id)->update(['reason' => 'tampered']);
    }

    #[Test]
    public function the_audit_log_cannot_be_deleted(): void
    {
        $this->probeRole('probe6');

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('append-only');

        DB::table('audit_log')->where('id', '>', $this->baseline)->delete();
    }

    #[Test]
    public function the_audit_log_cannot_be_truncated(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('append-only');

        DB::statement('TRUNCATE audit_log');
    }

    #[Test]
    public function documents_cannot_be_updated_or_deleted(): void
    {
        $user = User::factory()->create();

        $id = DB::table('documents')->insertGetId([
            'documentable_type' => 'probe', 'documentable_id' => 1, 'doc_type' => 'invoice',
            'disk' => 'documents', 'object_key' => 'ab/cd/abcdef',
            'original_name' => 'invoice.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 1024, 'sha256' => str_repeat('a', 64),
            'uploaded_by' => $user->id, 'uploaded_at' => now(),
        ]);

        foreach ([
            fn () => DB::table('documents')->where('id', $id)->update(['original_name' => 'tampered.pdf']),
            fn () => DB::table('documents')->where('id', $id)->delete(),
        ] as $attempt) {
            try {
                DB::transaction($attempt);
                $this->fail('Evidence must be immutable.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('not permitted', $e->getMessage());
            }
        }

        $this->assertSame('invoice.pdf', DB::table('documents')->where('id', $id)->value('original_name'));
    }
}
