<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The technical audit trail.
     *
     * Written by a database trigger rather than an Eloquent observer on purpose: a
     * trigger also captures changes made by a migration, an artisan command, a psql
     * session, or a developer taking a shortcut -- exactly the paths an auditor asks
     * about. Actor context arrives via session variables set by middleware
     * (set_config, session scope) or by PostingService (SET LOCAL, inside its
     * transaction). SET LOCAL outside a transaction is a silent no-op, which is why
     * the reader below tolerates a missing setting instead of assuming one.
     *
     * Partitioned by month from creation -- converting a populated table to
     * declarative partitioning later is a full rewrite.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE audit_log (
                id              bigserial       NOT NULL,
                occurred_at     timestamptz     NOT NULL DEFAULT clock_timestamp(),
                table_name      varchar(64)     NOT NULL,
                record_id       bigint,
                operation       char(1)         NOT NULL CHECK (operation IN ('I','U','D')),
                old_values      jsonb,
                new_values      jsonb,
                changed_columns text[],
                actor_user_id   bigint,
                actor_ip        inet,
                request_id      uuid,
                reason          text,
                PRIMARY KEY (id, occurred_at)
            ) PARTITION BY RANGE (occurred_at)
        SQL);

        DB::statement('CREATE INDEX audit_record_idx ON audit_log (table_name, record_id, occurred_at DESC)');
        DB::statement('CREATE INDEX audit_actor_idx  ON audit_log (actor_user_id, occurred_at DESC)');
        DB::statement('CREATE INDEX audit_new_gin    ON audit_log USING gin (new_values)');

        // Creates the month partition covering a given date, if absent.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION ensure_audit_partition(p_when date)
            RETURNS void AS $$
            DECLARE
                start_date date := date_trunc('month', p_when)::date;
                end_date   date := (date_trunc('month', p_when) + interval '1 month')::date;
                part_name  text := 'audit_log_' || to_char(start_date, 'YYYY_MM');
            BEGIN
                IF NOT EXISTS (SELECT 1 FROM pg_class WHERE relname = part_name) THEN
                    EXECUTE format(
                        'CREATE TABLE %I PARTITION OF audit_log FOR VALUES FROM (%L) TO (%L)',
                        part_name, start_date, end_date
                    );
                END IF;
            END;
            $$ LANGUAGE plpgsql
        SQL);

        // A default partition means a missed month can never lose an audit row.
        DB::statement('CREATE TABLE audit_log_default PARTITION OF audit_log DEFAULT');

        for ($offset = -1; $offset <= 12; $offset++) {
            $month = now()->startOfMonth()->addMonths($offset)->toDateString();
            DB::statement('SELECT ensure_audit_partition(?::date)', [$month]);
        }

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_row()
            RETURNS trigger AS $$
            DECLARE
                v_old       jsonb;
                v_new       jsonb;
                v_changed   text[];
                v_record_id bigint;
                v_actor     bigint;
                v_request   uuid;
                v_ip        inet;
            BEGIN
                BEGIN
                    v_actor := nullif(current_setting('app.user_id', true), '')::bigint;
                EXCEPTION WHEN others THEN v_actor := NULL;
                END;
                BEGIN
                    v_request := nullif(current_setting('app.request_id', true), '')::uuid;
                EXCEPTION WHEN others THEN v_request := NULL;
                END;
                BEGIN
                    v_ip := nullif(current_setting('app.actor_ip', true), '')::inet;
                EXCEPTION WHEN others THEN v_ip := NULL;
                END;

                IF (TG_OP = 'DELETE') THEN
                    v_old := to_jsonb(OLD);
                    v_record_id := (v_old ->> 'id')::bigint;
                ELSIF (TG_OP = 'UPDATE') THEN
                    v_old := to_jsonb(OLD);
                    v_new := to_jsonb(NEW);
                    SELECT array_agg(key ORDER BY key) INTO v_changed
                    FROM jsonb_each(v_new)
                    WHERE v_old -> key IS DISTINCT FROM v_new -> key;

                    -- A no-op update is noise, not evidence.
                    IF v_changed IS NULL THEN
                        RETURN NEW;
                    END IF;
                    v_record_id := (v_new ->> 'id')::bigint;
                ELSE
                    v_new := to_jsonb(NEW);
                    v_record_id := (v_new ->> 'id')::bigint;
                END IF;

                INSERT INTO audit_log (
                    table_name, record_id, operation, old_values, new_values,
                    changed_columns, actor_user_id, actor_ip, request_id, reason
                ) VALUES (
                    TG_TABLE_NAME, v_record_id, left(TG_OP, 1), v_old, v_new,
                    v_changed, v_actor, v_ip, v_request,
                    nullif(current_setting('app.audit_reason', true), '')
                );

                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql
        SQL);

        // Every financial table attaches its own trigger in its own migration.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION attach_audit(p_table text)
            RETURNS void AS $$
            BEGIN
                EXECUTE format('DROP TRIGGER IF EXISTS %I ON %I', 'audit_' || p_table, p_table);
                EXECUTE format(
                    'CREATE TRIGGER %I AFTER INSERT OR UPDATE OR DELETE ON %I
                     FOR EACH ROW EXECUTE FUNCTION audit_row()',
                    'audit_' || p_table, p_table
                );
            END;
            $$ LANGUAGE plpgsql
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP FUNCTION IF EXISTS attach_audit(text)');
        DB::statement('DROP FUNCTION IF EXISTS audit_row()');
        DB::statement('DROP FUNCTION IF EXISTS ensure_audit_partition(date)');
        DB::statement('DROP TABLE IF EXISTS audit_log');
    }
};
