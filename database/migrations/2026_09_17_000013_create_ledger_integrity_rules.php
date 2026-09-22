<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // A posted entry must equal the sum of its own lines. This is a CONSTRAINT
        // TRIGGER rather than a CHECK because it has to see the child rows, and
        // because it must fire at COMMIT -- the header is written before the lines.
        // (PostgreSQL cannot mark a CHECK constraint DEFERRABLE.)
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION je_assert_balanced()
            RETURNS trigger AS $$
            DECLARE
                v_debit  numeric(20,4);
                v_credit numeric(20,4);
                v_lines  integer;
            BEGIN
                IF NEW.status <> 'posted' THEN
                    RETURN NULL;
                END IF;

                SELECT COALESCE(sum(debit_amount), 0),
                       COALESCE(sum(credit_amount), 0),
                       count(*)
                  INTO v_debit, v_credit, v_lines
                  FROM journal_lines
                 WHERE journal_entry_id = NEW.id;

                IF v_lines < 2 THEN
                    RAISE EXCEPTION 'Journal entry % has % line(s); a double entry needs at least two.',
                        NEW.id, v_lines USING ERRCODE = 'check_violation';
                END IF;

                IF v_debit <> v_credit THEN
                    RAISE EXCEPTION 'Journal entry % does not balance: debits %, credits %.',
                        NEW.id, v_debit, v_credit USING ERRCODE = 'check_violation';
                END IF;

                IF v_debit <> NEW.total_debit OR v_credit <> NEW.total_credit THEN
                    RAISE EXCEPTION 'Journal entry % header totals (%/%) disagree with its lines (%/%).',
                        NEW.id, NEW.total_debit, NEW.total_credit, v_debit, v_credit
                        USING ERRCODE = 'check_violation';
                END IF;

                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE CONSTRAINT TRIGGER je_balanced_at_commit
            AFTER INSERT OR UPDATE ON journal_entries
            DEFERRABLE INITIALLY DEFERRED
            FOR EACH ROW EXECUTE FUNCTION je_assert_balanced()
        SQL);

        // Posted entries are append-only. The only permitted mutation is the one
        // reversal leaves behind on the original, so a correction stays visible
        // rather than rewriting history.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION je_block_posted_update()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.status NOT IN ('posted', 'reversed') THEN
                    RETURN NEW;
                END IF;

                IF (to_jsonb(NEW) - 'status' - 'reversed_by_entry_id' - 'updated_at')
                   IS DISTINCT FROM
                   (to_jsonb(OLD) - 'status' - 'reversed_by_entry_id' - 'updated_at') THEN
                    RAISE EXCEPTION
                        'Journal entry % is posted and cannot be modified. Reverse it instead.',
                        OLD.id USING ERRCODE = 'check_violation';
                END IF;

                IF NEW.status NOT IN ('posted', 'reversed') THEN
                    RAISE EXCEPTION
                        'Journal entry % cannot leave the posted state.',
                        OLD.id USING ERRCODE = 'check_violation';
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER je_no_update_when_posted
            BEFORE UPDATE ON journal_entries
            FOR EACH ROW EXECUTE FUNCTION je_block_posted_update()
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION jl_block_posted_update()
            RETURNS trigger AS $$
            BEGIN
                IF OLD.posted_at IS NOT NULL THEN
                    RAISE EXCEPTION
                        'Journal line % belongs to a posted entry and cannot be modified.',
                        OLD.id USING ERRCODE = 'check_violation';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER jl_no_update_when_posted
            BEFORE UPDATE ON journal_lines
            FOR EACH ROW EXECUTE FUNCTION jl_block_posted_update()
        SQL);

        // Conditional, so a draft line can still be removed from the repeater. An
        // unconditional rule here would make every draft row deletion a silent no-op.
        DB::statement(<<<'SQL'
            CREATE RULE jl_no_delete_when_posted AS
            ON DELETE TO journal_lines
            WHERE OLD.posted_at IS NOT NULL
            DO INSTEAD NOTHING
        SQL);

        DB::statement(<<<'SQL'
            CREATE RULE je_no_delete_when_posted AS
            ON DELETE TO journal_entries
            WHERE OLD.status IN ('posted', 'reversed')
            DO INSTEAD NOTHING
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP RULE IF EXISTS je_no_delete_when_posted ON journal_entries');
        DB::statement('DROP RULE IF EXISTS jl_no_delete_when_posted ON journal_lines');
        DB::statement('DROP TRIGGER IF EXISTS jl_no_update_when_posted ON journal_lines');
        DB::statement('DROP TRIGGER IF EXISTS je_no_update_when_posted ON journal_entries');
        DB::statement('DROP TRIGGER IF EXISTS je_balanced_at_commit ON journal_entries');
        DB::statement('DROP FUNCTION IF EXISTS jl_block_posted_update()');
        DB::statement('DROP FUNCTION IF EXISTS je_block_posted_update()');
        DB::statement('DROP FUNCTION IF EXISTS je_assert_balanced()');
    }
};
