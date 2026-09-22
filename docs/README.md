# SHH-01 Financial & Accounting System — implementation notes

The contract is the specification in [`specification/`](specification/README.md): Document A (business requirements, Arabic), Document B (technical specification) and Document C (data dictionary). This page maps it to the code and records the decisions the specification left open.

## Governing rule

No figure is stored anywhere except `journal_lines` (Document B §1.1). Balances, subledgers, statements and dashboard figures are aggregated on read. `AcceptanceCriteriaTest::no_figure_is_stored_outside_journal_lines` fails if any other table grows an IQD column that is not source data or external evidence.

## Where things are

| Module | Code |
|---|---|
| M00 Parameters | `app/Domain/Organisation/{Parameter,ParameterResolver,ParameterService}` — effective-dated, history never rewritten |
| M01/M02 Chart, dimensions, parties | `app/Domain/MasterData`, `app/Domain/Funding`; seeded from `database/data/shh` by `database/seeders/Shh` |
| M03 Journal and posting engine | `app/Domain/Ledger` — `JournalService` (drafts), `Workflow/JournalWorkflow` (Draft → Posted), `Posting/PostingService`, `Validation/Rules/Vr*` (one class per rule), `Rules/SelectorParser` (posting rules as data) |
| M04 Advances | `app/Domain/Advances` — outstanding amounts derived from linked ledger lines |
| M05 Shareholder funding | rules VR-20/21; reports RPT-08b, 11, 12, 20 |
| M06 Contractors | `app/Domain/Contractors/SubledgerQuery`; rules VR-31–34; RPT-16, 17 |
| M07 Reporting | `app/Domain/Reporting` — `StatementEngine` (mapping-driven), `LedgerQuery`, `Filters/`, `Reports/Rpt*`, `Export/` (Excel, PDF) |
| M09 Controls | `app/Domain/Controls` — duplicate detection, exceptions register |
| M10 Audit | trigger-written, append-only `audit_log`; field-level view `audit_logs`; `Shared/Audit/AuditRecorder` for application events |
| M11 Cash | `app/Domain/Cash` — book balance computed, reconciliations with variance |
| M12 CIP / M14 Revenue | VR-39/40; `app/Domain/Revenue/GovernmentShareService`; RPT-18, 19 |
| M15 Closing | `app/Domain/Closing` — `PeriodCloseService`, `Gates/*`, 25-task checklist |
| M16 Documents | `app/Domain/Shared/Documents` — content-addressed, SHA-256, immutable |
| M17 Security | 8 roles from tab 19 (`AccessControlSeeder`); lockout after 5 failures; 20-minute idle and 12-hour absolute sessions; Argon2id |
| M18 Migration | `app/Domain/Migration`; `php artisan shh:migrate:file1`, `shh:migrate:controls`; RPT-29 |

Every validation rule (VR-01…60) and UAT case (UAT-001…052) has a test tagged with its code; `TraceabilityTest` enforces that.

## Decisions the specification left open

1. **Group accounts.** The chart's 15 parent codes (111000 … 610000) are stored as non-posting level-1 rows so the tree has one parent column. "145 accounts" counts exclude them.
2. **Journal number.** Drawn at posting from a gapless sequence (`JV-2026-00001`). Migrated entries keep their source numbers (`JV-0001`).
3. **Ledger statuses.** Reports aggregate `Posted` **and** `Reversed` entries: a reversed entry and its mirror both stay in the ledger and net to nil.
4. **Date filter.** Ledger reports filter on the posting date, so undated historical entries are never dropped. The transaction date is a separate filter.
5. **Posting rules PR-36…43.** Tab 13 has no rule for TT-04, 11, 22, 25, 31 and 36. Rules derived from their tab 12 debit/credit logic are loaded **Pending**; those transaction types stay blocked until the Finance Manager approves the rules.
6. **Approval paths.** The role matrix (tab 19) governs: only the Finance Manager approves and posts. A type's approval path decides only whether a Senior Accountant review precedes approval and whether a board reference is needed.
7. **Year-end transfer.** TT-38 is the one posting a Final Close period accepts (VR-53); lock the period afterwards.
8. **Duplicate detection.** The value tests (same amount, counterparty, period / near date) compare entries of the same transaction type, so an advance and its settlement are not flagged against each other.
9. **Migrated advances.** The source records custody as flows per account, so the migration opens one historic advance per holder and account (MIG-17); its outstanding amount is the account's net position, including a net credit.
10. **Documents on migrated entries.** File 1 carries no document status. Migrated entries load as `Missing` without raising one exception each; the register's own rows carry the document gaps.
11. **Government share rounding.** Half-up to whole dinars.

## Open items needing a decision or data

1. **The authoritative workbook** (`قيود شمس الهيلان - مصحح تمويل الراشدية.xlsx`) — needed for the historical ledger, the duplicate flags and MC-01…30. Also set the `migration_cutoff_date` and `go_live_date` parameters, and name the external sign-off.
2. **Approve or amend PR-36…43** (`database/data/shh/derived_posting_rules.csv`).
3. **Values not given:** the VR-29 contract threshold (while unset, a contract is always required), the default retention %.
4. **Custodians and responsible accountants** for the 8 cash accounts — period close requires them (RE-18).
5. **Lockout policy.** Accounts currently stay locked until a System Administrator unlocks them (`config/shh.php` → `security.lockout_minutes` for a timed lock).
6. **Review** the English wording of the closing tasks and the Arabic validation messages.
7. **Reclassification log (MIG-16).** Its sheet layout is not in Document C; the importer will be extended once the workbook is available.

## Operations

- **Rebuild:** `php artisan migrate:fresh --seed` (development users: `*@example.com` / `password`, one per role).
- **Database roles.** Run migrations as an owner role and the application as `shh_app`. With `shh_app` present, the migrations grant it `INSERT, SELECT` only on `audit_log`. Unlocking a Locked period is possible only as the owner, through `SELECT admin_unlock_period(<id>, '<reason>')`.
- **Scheduler:** `shh:advances:flag-overdue` runs daily at 06:00 (`php artisan schedule:work` or cron).
- **Migration:** `php artisan shh:migrate:file1 <workbook> --user=<fm email>` (dry run), then add `--commit --signoff=<ref> [--mapping=<csv>]`. `php artisan test --filter=the_authoritative_workbook_reconciles` with `SHH_FILE1_PATH` set runs UAT-051.
- **Backups (Document B §10):** full nightly, WAL archiving every 15 minutes, 90-day retention, one copy off-site; include the `documents` disk. Restore into a clean environment quarterly and record the result — an untested restore counts as absent.
- **Infrastructure still to provide:** encryption at rest, a virus scanner bound to `App\Domain\Shared\Documents\VirusScanner` (the default accepts everything), TLS.
