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
        Schema::create('cost_centres', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->nullable()->constrained();  // null = group-shared
            $table->foreignId('parent_id')->nullable()->constrained('cost_centres');
            $table->string('code', 20);
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->string('cost_centre_type', 20);
            // Department and branch are attributes OF the cost centre rather than
            // separate posting dimensions: a centre belongs to exactly one of each,
            // and making them independent invites contradictory combinations.
            $table->string('department', 80)->nullable();
            $table->string('branch', 80)->nullable();
            // Survives a departmental merge, so comparatives can be restated without
            // rebuilding every report.
            $table->string('reporting_group', 60)->nullable();
            $table->foreignId('manager_user_id')->nullable()->constrained('users');
            $table->unsignedSmallInteger('depth')->default(0);
            $table->boolean('is_postable')->default(true);
            $table->boolean('allows_revenue')->default(true);
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['entity_id', 'code']);
            $table->index('parent_id');
        });

        // ltree is not a Blueprint type; rawColumn is the supported escape hatch.
        // "all descendants of X" becomes `path <@ 'X'` against a GiST index instead of
        // a recursive CTE per report -- and cost centre rollup is the single most
        // frequent query in management reporting.
        Schema::table('cost_centres', function (Blueprint $table): void {
            $table->rawColumn('path', 'ltree')->nullable();
        });
        DB::statement('CREATE INDEX cost_centres_path_gist ON cost_centres USING gist (path)');

        DB::statement("ALTER TABLE cost_centres ADD CONSTRAINT cost_centre_type_valid CHECK (cost_centre_type IN ('operating','support','project','admin','statistical'))");
        DB::statement('ALTER TABLE cost_centres ADD CONSTRAINT cost_centre_dates_valid CHECK (effective_to IS NULL OR effective_from IS NULL OR effective_to >= effective_from)');
        DB::statement('CREATE RULE cost_centres_no_delete AS ON DELETE TO cost_centres DO INSTEAD NOTHING');

        // Who may see and post to which centres (docs/05 §5.8).
        Schema::create('cost_centre_user', function (Blueprint $table): void {
            $table->foreignId('cost_centre_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('access_level', 20)->default('view');   // view | post | manage
            $table->primary(['cost_centre_id', 'user_id']);
        });

        // High-cardinality by design (10s to 1000s). Deliberately NOT keyed into
        // gl_balances -- docs/05 §5.7 -- or the balance store inverts its own benefit.
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('entity_id')->nullable()->constrained();
            $table->string('code', 30);
            $table->string('name', 200);
            $table->string('name_ar', 200)->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();   // FK added with AR
            $table->foreignId('cost_centre_id')->nullable()->constrained();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['entity_id', 'code']);
        });

        // Rare or optional analysis axes that do not earn a column on journal_lines.
        Schema::create('dimensions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });

        Schema::create('dimension_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('dimension_id')->constrained()->cascadeOnDelete();
            $table->string('code', 40);
            $table->string('name', 150);
            $table->string('name_ar', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();

            $table->unique(['dimension_id', 'code']);
        });

        foreach (['cost_centres', 'cost_centre_user', 'projects', 'dimensions', 'dimension_members'] as $table) {
            DB::statement('SELECT attach_audit(?)', [$table]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dimension_members');
        Schema::dropIfExists('dimensions');
        Schema::dropIfExists('projects');
        Schema::dropIfExists('cost_centre_user');
        DB::statement('DROP RULE IF EXISTS cost_centres_no_delete ON cost_centres');
        Schema::dropIfExists('cost_centres');
    }
};
