<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Domain\Access\Role;
use App\Models\User;
use App\Support\DatabaseContext;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    #[Test]
    public function it_records_inserts_updates_and_deletes(): void
    {
        DB::table('audit_log')->delete();

        $role = Role::create([
            'name' => 'probe', 'label_en' => 'Probe', 'is_read_only' => false,
            'is_system' => false, 'requires_mfa' => false,
        ]);
        $role->update(['label_en' => 'Probe Renamed']);
        $role->delete();

        $entries = DB::table('audit_log')
            ->where('table_name', 'roles')
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $entries);
        $this->assertSame(['I', 'U', 'D'], $entries->pluck('operation')->all());
    }

    #[Test]
    public function it_records_which_columns_changed(): void
    {
        DB::table('audit_log')->delete();

        $role = Role::create([
            'name' => 'probe2', 'label_en' => 'Before', 'is_read_only' => false,
            'is_system' => false, 'requires_mfa' => false,
        ]);
        $role->update(['label_en' => 'After', 'requires_mfa' => true]);

        $update = DB::table('audit_log')
            ->where('table_name', 'roles')->where('operation', 'U')->first();

        $this->assertNotNull($update);

        // PostgreSQL text[] arrives as a literal like {label_en,requires_mfa,updated_at}
        $changed = explode(',', trim((string) $update->changed_columns, '{}'));

        $this->assertContains('label_en', $changed);
        $this->assertContains('requires_mfa', $changed);
        $this->assertSame('Before', json_decode((string) $update->old_values, true)['label_en']);
        $this->assertSame('After', json_decode((string) $update->new_values, true)['label_en']);
    }

    #[Test]
    public function it_attributes_the_change_to_the_acting_user(): void
    {
        DB::table('audit_log')->delete();

        DatabaseContext::actingAs(4242, 'unit test');

        Role::create([
            'name' => 'probe3', 'label_en' => 'Attributed', 'is_read_only' => false,
            'is_system' => false, 'requires_mfa' => false,
        ]);

        $entry = DB::table('audit_log')->where('table_name', 'roles')->first();

        $this->assertNotNull($entry);
        $this->assertSame(4242, (int) $entry->actor_user_id);
        $this->assertSame('unit test', $entry->reason);

        DatabaseContext::clear();
    }

    #[Test]
    public function a_no_op_update_is_not_recorded(): void
    {
        $role = Role::create([
            'name' => 'probe4', 'label_en' => 'Same', 'is_read_only' => false,
            'is_system' => false, 'requires_mfa' => false,
        ]);

        DB::table('audit_log')->delete();

        // Touch the row with identical values, bypassing Eloquent's dirty check.
        DB::table('roles')->where('id', $role->id)->update(['label_en' => 'Same']);

        $this->assertSame(0, DB::table('audit_log')->where('table_name', 'roles')->count());
    }

    #[Test]
    public function attachments_cannot_be_updated_or_deleted(): void
    {
        $user = User::factory()->create();

        $id = DB::table('attachments')->insertGetId([
            'attachable_type' => 'probe', 'attachable_id' => 1,
            'disk' => 'documents', 'object_key' => 'ab/cd/abcdef',
            'original_name' => 'invoice.pdf', 'mime_type' => 'application/pdf',
            'size_bytes' => 1024, 'sha256' => str_repeat('a', 64),
            'uploaded_by' => $user->id, 'uploaded_at' => now(),
        ]);

        DB::table('attachments')->where('id', $id)->update(['original_name' => 'tampered.pdf']);
        DB::table('attachments')->where('id', $id)->delete();

        $row = DB::table('attachments')->where('id', $id)->first();

        $this->assertNotNull($row, 'Evidence must not be deletable.');
        $this->assertSame('invoice.pdf', $row->original_name, 'Evidence must not be editable.');
    }
}
