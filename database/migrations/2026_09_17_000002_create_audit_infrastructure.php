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
     *
     * Append-only (Document B §3.4, acceptance criterion 8). UPDATE, DELETE and
     * TRUNCATE are refused by trigger for every principal, and the runtime role
     * (shh_app, where it exists) holds INSERT and SELECT only. Operation 'A' marks a
     * row the application writes itself -- a login, a refused posting, an export,
     * an imported reclassification -- rather than one a table trigger wrote.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE TABLE audit_log (
                id              bigserial       NOT NULL,
                occurred_at     timestamptz     NOT NULL DEFAULT clock_timestamp(),
                table_name      varchar(64)     NOT NULL,
                record_id       bigint,
                operation       char(1)         NOT NULL CHECK (operation IN ('I','U','D','A')),
                action          varchar(40),
                object_type     varchar(64),
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
        DB::statement('CREATE INDEX audit_action_idx ON audit_log (action, occurred_at DESC)');

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
                    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'shh_app') THEN
                        EXECUTE format('REVOKE UPDATE, DELETE, TRUNCATE ON %I FROM shh_app', part_name);
                    END IF;
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
                v_action    text;
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

                -- A service names the business action it is performing (submit,
                -- post, reverse, reopen...); without one the row says what SQL did.
                v_action := coalesce(
                    nullif(current_setting('app.audit_action', true), ''),
                    lower(TG_OP)
                );

                INSERT INTO audit_log (
                    table_name, object_type, record_id, operation, action, old_values, new_values,
                    changed_columns, actor_user_id, actor_ip, request_id, reason
                ) VALUES (
                    TG_TABLE_NAME, TG_TABLE_NAME, v_record_id, left(TG_OP, 1), v_action, v_old, v_new,
                    v_changed, v_actor, v_ip, v_request,
                    nullif(current_setting('app.audit_reason', true), '')
                );

                RETURN COALESCE(NEW, OLD);
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION audit_log_append_only()
            RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'audit_log is append-only: % is not permitted', TG_OP
                    USING ERRCODE = 'insufficient_privilege';
            END;
            $$ LANGUAGE plpgsql
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER audit_log_no_update_delete
                BEFORE UPDATE OR DELETE ON audit_log
                FOR EACH ROW EXECUTE FUNCTION audit_log_append_only()
        SQL);

        DB::statement(<<<'SQL'
            CREATE TRIGGER audit_log_no_truncate
                BEFORE TRUNCATE ON audit_log
                FOR EACH STATEMENT EXECUTE FUNCTION audit_log_append_only()
        SQL);

        // The runtime principal. Created by deployment, not by this migration, so
        // the grant is applied only where the role already exists.
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'shh_app') THEN
                    REVOKE UPDATE, DELETE, TRUNCATE ON audit_log FROM shh_app;
                    GRANT INSERT, SELECT ON audit_log TO shh_app;
                END IF;
            END
            $$
        SQL);

        // The field-level view Document B §7 describes: one row per changed field.
        // RPT-26 and RPT-32 read this, never the raw table.
        DB::statement(<<<'SQL'
            CREATE VIEW audit_logs AS
            SELECT a.id                                   AS audit_id,
                   a.object_type,
                   a.table_name,
                   a.record_id                            AS object_id,
                   a.action,
                   a.operation,
                   f.field_name,
                   a.old_values -> f.field_name           AS old_value,
                   a.new_values -> f.field_name           AS new_value,
                   a.reason,
                   a.actor_user_id                        AS user_id,
                   a.actor_ip                             AS ip_address,
                   a.request_id,
                   a.occurred_at                          AS action_at
              FROM audit_log a
              LEFT JOIN LATERAL unnest(
                  CASE WHEN a.operation = 'U' THEN a.changed_columns ELSE ARRAY[NULL::text] END
              ) AS f(field_name) ON true
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
        DB::statement('DROP VIEW IF EXISTS audit_logs');
        DB::statement('DROP FUNCTION IF EXISTS attach_audit(text)');
        DB::statement('DROP FUNCTION IF EXISTS audit_row()');
        DB::statement('DROP FUNCTION IF EXISTS ensure_audit_partition(date)');
        DB::statement('DROP TABLE IF EXISTS audit_log');
        DB::statement('DROP FUNCTION IF EXISTS audit_log_append_only()');
    }
};
