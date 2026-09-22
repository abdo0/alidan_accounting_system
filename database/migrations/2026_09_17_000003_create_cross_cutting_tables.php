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
        // Gapless numbering (VR-10). One counter row per scope; taken with
        // SELECT ... FOR UPDATE inside the posting transaction, as late as possible
        // so validation does not hold the serialisation lock.
        Schema::create('sequence_counters', function (Blueprint $table): void {
            $table->string('scope', 40);            // jv | exception | ...
            $table->string('scope_key', 120);       // company:1|fy:2027
            $table->unsignedBigInteger('next_value')->default(1);
            $table->string('prefix', 20)->nullable();
            $table->unsignedSmallInteger('pad_to')->default(5);
            $table->timestampsTz();

            $table->primary(['scope', 'scope_key']);
        });

        // Checked with INSERT ... ON CONFLICT DO NOTHING inside the posting
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

        // Evidence (M16). A journal's document status says whether evidence is
        // Complete, Partial or Missing; these rows are the evidence itself.
        // Content-addressed and never overwritten: an auditor can re-hash the stored
        // object and prove it is the file that was uploaded (Document B §8).
        Schema::create('documents', function (Blueprint $table): void {
            $table->id();
            $table->string('documentable_type', 100);
            $table->unsignedBigInteger('documentable_id');
            $table->unsignedBigInteger('journal_line_id')->nullable();
            $table->string('doc_type', 40);
            $table->string('doc_ref', 120)->nullable();
            $table->date('doc_date')->nullable();
            $table->text('description')->nullable();
            $table->string('disk', 40)->default('documents');
            $table->string('object_key', 512);
            $table->string('original_name', 255);
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestampTz('uploaded_at')->useCurrent();

            $table->index(['documentable_type', 'documentable_id']);
            $table->index('sha256');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE documents ADD CONSTRAINT documents_doc_type_valid CHECK (doc_type IN (
                'invoice', 'receipt', 'payment_voucher', 'receipt_voucher', 'contract',
                'certificate', 'board_minute', 'bank_statement', 'approval', 'other'
            ))
        SQL);

        DB::statement('SELECT attach_audit(?)', ['documents']);

        // Evidence is not edited or withdrawn. A trigger rather than a RULE so that
        // the attempt fails loudly instead of silently doing nothing.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION refuse_change()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION '% on % is not permitted (%)', TG_OP, TG_TABLE_NAME, TG_ARGV[0]
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER documents_immutable BEFORE UPDATE OR DELETE ON documents
                FOR EACH ROW EXECUTE FUNCTION refuse_change('evidence is immutable')
        SQL);
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        DB::statement('DROP FUNCTION IF EXISTS refuse_change()');
        Schema::dropIfExists('idempotency_keys');
        Schema::dropIfExists('sequence_counters');
    }
};
