<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The approval history of any approvable object (Document C tab 20). Every
     * workflow transition writes one row here as well as the audit row
     * (Document B §4.2). A board decision recorded by ROLE-08 is a row too.
     */
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->string('object_type', 60);
            $table->unsignedBigInteger('object_id');
            $table->string('action', 20);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('comment')->nullable();
            $table->string('approval_ref', 120)->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestampTz('action_at')->useCurrent();

            $table->index(['object_type', 'object_id']);
        });

        DB::statement(<<<'SQL'
            ALTER TABLE approvals ADD CONSTRAINT approvals_action_valid CHECK (action IN (
                'submit', 'review', 'approve', 'reject', 'post', 'reverse', 'reclassify',
                'board_decision', 'soft_close', 'final_close', 'reopen', 'lock'
            ))
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER approvals_immutable BEFORE UPDATE OR DELETE ON approvals
                FOR EACH ROW EXECUTE FUNCTION refuse_change('approval history is permanent')
        SQL);

        DB::statement('SELECT attach_audit(?)', ['approvals']);
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
