<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gapless numbering for BOTH journal entries and subledger documents.
        // One counter row per scope; taken with SELECT ... FOR UPDATE inside the
        // posting transaction, as late as possible so validation does not hold the
        // per-journal serialisation lock.
        Schema::create('sequence_counters', function (Blueprint $table): void {
            $table->string('scope', 40);            // journal_entry | sales_invoice | ...
            $table->string('scope_key', 120);       // entity:1|journal:GJ|fy:2027
            $table->unsignedBigInteger('next_value')->default(1);
            $table->string('prefix', 20)->nullable();
            $table->unsignedSmallInteger('pad_to')->default(5);
            $table->timestampsTz();

            $table->primary(['scope', 'scope_key']);
        });

        // V-16. Checked with INSERT ... ON CONFLICT DO NOTHING inside the posting
        // transaction -- a SELECT-then-INSERT would be a TOCTOU race and would defeat
        // the point. Also what makes deadlock retry safe.
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 40);
            $table->string('key', 160);
            $table->char('request_hash', 64);
            $table->string('result_type', 40)->nullable();
            $table->unsignedBigInteger('result_id')->nullable();
            $table->jsonb('response')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['scope', 'key']);
        });

        // Evidence. P10/FR-008 make a source document a posting precondition, so this
        // is not an optional convenience. Content-addressed and never overwritten:
        // retrofitting content-addressing after files exist means you can never prove
        // the integrity of the earlier ones.
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->string('attachable_type', 100);
            $table->unsignedBigInteger('attachable_id');
            $table->string('disk', 40)->default('documents');
            $table->string('object_key', 512);       // immutable, content-addressed
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('document_type', 40)->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestampTz('uploaded_at')->useCurrent();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index('sha256');
        });

        DB::statement('SELECT attach_audit(?)', ['attachments']);

        // Attachments are evidence; evidence is not edited or withdrawn.
        DB::statement('CREATE RULE attachments_no_update AS ON UPDATE TO attachments DO INSTEAD NOTHING');
        DB::statement('CREATE RULE attachments_no_delete AS ON DELETE TO attachments DO INSTEAD NOTHING');
    }

    public function down(): void
    {
        DB::statement('DROP RULE IF EXISTS attachments_no_delete ON attachments');
        DB::statement('DROP RULE IF EXISTS attachments_no_update ON attachments');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('sequence_counters');
    }
};
