# 02 — System Architecture

**Reads from:** [01 — Source Analysis](01-source-analysis.md)
**Stack (observed, not assumed):** Laravel 13.32 · PHP 8.4 · Filament 5.8 · PostgreSQL 16.10 · Livewire 3

---

## 2.1 Architectural stance

One decision dominates every other in an accounting system, so I will state it
first and defend it:

> **The General Ledger is the only writer of financial truth, and every other
> module is a *producer of journal entries* rather than a keeper of its own
> balances.**

AR does not "know" what a customer owes; it knows about invoices and receipts, and
the balance is the GL control account. Inventory does not "know" stock value; it
owns quantities and produces the valuation entries. This is unglamorous and it is
the difference between a system that reconciles and a system that spends every
month-end hunting for a 4,000 IQD difference between a subledger and its control
account.

The corollary — equally important — is that **the posting engine is the most
valuable code in the product**. It deserves the most tests, the least cleverness,
and no shortcuts for "special cases".

A second stance, given the team size implied by assumption A-06: **build a modular
monolith, not microservices.** A double-entry ledger is a single transactional
consistency boundary. Splitting AR and GL across a network boundary means
inventing distributed transactions to preserve an invariant (Σ Dr = Σ Cr) that
PostgreSQL will give you for free inside one `BEGIN`. Modules are enforced as
code boundaries, not deployment boundaries.

---

## 2.2 Module map

Doc B's pipeline maps directly onto the module boundaries. Modules above the line
are *entry* modules (they create documents and propose entries); the Ledger is the
*system of record*; modules below are *consumers*.

```
┌──────────────────────── ENTRY / SUBLEDGER MODULES ─────────────────────────┐
│                                                                            │
│  AR            AP            CASH & BANK      FIXED ASSETS                 │
│  customers     vendors       cash book        asset register               │
│  invoices      bills         petty cash       depreciation runs            │
│  credit notes  debit notes   receipts         disposals                    │
│  receipts      payments      bank recon       revaluation                  │
│                                                                            │
│  INVENTORY     PAYROLL       TAX              PROCUREMENT (optional)       │
│  items         employees     tax codes        PO / GRN                     │
│  stock moves   pay runs      determination    3-way match                  │
│  valuation     payslips      withholding                                   │
│                                                                            │
└──────────────────────────────────┬─────────────────────────────────────────┘
                                   │  JournalEntryDraft  (the ONLY interface)
                                   ▼
╔════════════════════════════════════════════════════════════════════════════╗
║                        GENERAL LEDGER  (system of record)                  ║
║                                                                            ║
║   Chart of Accounts · Journals (books of prime entry) · Journal Entries     ║
║   Journal Lines · Dimensions (incl. COST CENTRE) · Fiscal Calendar          ║
║   Posting Engine · Balance Store · Period Close · Audit Trail               ║
╚══════════════════════════════════════════════════════════════════════════╤═╝
                                   │
┌──────────────────────────────────▼─────────────────────────────────────────┐
│  REPORTING          CONSOLIDATION        BUDGETING        ALLOCATIONS       │
│  TB · P&L · BS      multi-entity         budget vs        cost centre       │
│  SCF · SOCE         FX translation       actual           allocation runs   │
│  ageing · KPIs      eliminations         forecasts        driver data       │
└────────────────────────────────────────────────────────────────────────────┘

        ┌────────────────── CROSS-CUTTING (every module) ──────────────────┐
        │  Identity & RBAC │ Approvals │ Audit │ Numbering │ Attachments    │
        │  Notifications   │ Jobs/Queue │ Import/Export │ Integration Bus   │
        └───────────────────────────────────────────────────────────────────┘
```

### The one interface that matters

Every subledger talks to the GL through a single value object. Nothing else may
insert into `journal_lines`.

```php
// app/Domain/Ledger/JournalEntryDraft.php  (illustrative)
final readonly class JournalEntryDraft
{
    public function __construct(
        public int $entityId,
        public string $journalCode,        // SDB, PDB, RIB, ROB, CB, PCB, GJ
        public CarbonImmutable $entryDate,
        public string $sourceType,         // e.g. 'sales_invoice'
        public int $sourceId,
        public string $sourceDocumentNo,   // the serial ref Doc B requires
        public string $description,
        /** @var JournalLineDraft[] */
        public array $lines,
        public ?string $currencyCode = null,
        public ?int $reversesEntryId = null,
    ) {}
}
```

The posting service accepts a draft, validates every invariant in
[04 §4.2](04-transactions-workflows.md), and either commits atomically or throws.
There is no partial post.

### Suggested code layout

```
app/
├── Domain/
│   ├── Ledger/        Account, Journal, JournalEntry, JournalLine, Posting/, Balances/, Close/
│   ├── Dimensions/    CostCentre, Project, Department, DimensionPolicy
│   ├── Receivables/   Customer, SalesInvoice, CustomerReceipt, CreditNote, Ageing/
│   ├── Payables/      Vendor, VendorBill, VendorPayment, DebitNote
│   ├── Cash/          BankAccount, CashBook, PettyCash, Reconciliation/
│   ├── Assets/        FixedAsset, DepreciationRun, Disposal
│   ├── Inventory/     Item, StockMove, Valuation/
│   ├── Payroll/       Employee, PayRun, Payslip
│   ├── Tax/           TaxCode, Determination/, Returns/
│   ├── Budgeting/     Budget, BudgetLine, Variance/
│   ├── Allocation/    AllocationRule, Driver, AllocationRun
│   └── Shared/        Money, CurrencyCode, FiscalPeriod, Sequence, AuditTrail
├── Filament/
│   ├── Resources/     one per aggregate root
│   ├── Pages/         Trial Balance, Close Cockpit, Allocation Console
│   └── Widgets/       KPI tiles, close-readiness, unposted batches
└── Support/
```

`Domain/` holds no framework-coupled logic beyond Eloquent models. Posting rules
are pure and unit-testable without HTTP or Filament.

---

## 2.3 Multi-entity model (G-01, A-02)

**Recommendation: one database, `entity_id` on every financial table, enforced by
PostgreSQL Row-Level Security.**

Alternatives considered:

| Option | Verdict |
|---|---|
| Database per entity | Rejected. Consolidation becomes cross-database ETL; shared master data (CoA, currencies, cost centres) drifts; 14 backup targets instead of one. |
| Schema per entity | Rejected for the same consolidation reason, with added migration pain (N schemas to migrate in lockstep). |
| **Shared tables + `entity_id` + RLS** | **Recommended.** Consolidation is a `GROUP BY`. One migration path. RLS gives defence in depth if application code forgets a scope. |

Implementation notes:
- `entities` is a hierarchy (group → sub-group → legal entity) so consolidation
  can roll up at any node.
- Each entity has its own functional currency, fiscal calendar and CoA *subset*
  (accounts are group-wide; `entity_account_settings` marks which are usable where).
- A Laravel global scope applies `entity_id IN (…user's entities)`; RLS applies the
  same predicate at the database level using a session variable set per request.
- Cross-entity entries are **forbidden in a single journal entry**. Intercompany
  is two entries linked by an `intercompany_link_id` — see [04 §4.6](04-transactions-workflows.md).

---

## 2.4 Deployment model

Given assumption A-10 (≤50 users, ≤500k lines/year initially), this does not need
Kubernetes and should not have it.

**Recommended topology — start here:**

```
                     ┌──────────────────┐
   users ── HTTPS ──▶│  nginx + PHP-FPM │  app server (Laravel + Filament)
                     │  Laravel Horizon │  queue workers (same or separate box)
                     └────────┬─────────┘
                              │
              ┌───────────────┼────────────────┐
              ▼               ▼                ▼
      ┌──────────────┐ ┌────────────┐  ┌──────────────┐
      │ PostgreSQL16 │ │   Redis    │  │  S3/MinIO    │
      │  primary     │ │ cache/queue│  │  attachments │
      │  + streaming │ │  /locks    │  │  (evidence)  │
      │    replica   │ └────────────┘  └──────────────┘
      └──────────────┘
```

Deliberate choices:

- **On-premise or DO droplet, not shared hosting.** Financial data plus the
  audit-trail immutability requirement means you must control backups and access.
- **A streaming replica is not optional.** It is both your DR posture and where
  heavy reports run, so a month-end report pack cannot slow down posting.
- **Attachments go to object storage, not the database.** Al-Idan already runs
  MinIO in-house (`idan-bkp-01`, `idan-bkp-03`) with an established backup chain —
  using it for evidence storage reuses proven infrastructure rather than inventing
  a second one. Store an immutable object key + SHA-256 in the DB; never overwrite
  an object, always version.
- **Queues for everything slow**: depreciation runs, allocation runs, revaluation,
  report generation, statement PDF rendering, integration polling. Posting itself
  is synchronous — a user must know immediately whether their entry posted.
- **Backups:** PostgreSQL PITR (WAL archiving) with a tested restore, not just
  `pg_dump`. Al-Idan's existing `db-backup` pattern (hourly dump + a live restored
  copy that is actually queried) is a genuinely good model and worth repeating: a
  backup nobody has restored is a hypothesis.

**When to scale up** (not before): separate the queue workers onto their own host
when a depreciation or allocation run starts delaying interactive requests;
promote reporting to the replica when month-end contention appears; consider
`journal_lines` partitioning at the thresholds in §2.5.

---

## 2.5 Scalability & performance

Accounting systems have an unusual load profile: writes are modest and bursty
(month-end), reads are aggregate-heavy and get slower every year because the table
only ever grows. The design responses:

**1. Materialise balances; never sum the ledger for a routine report.**
A `gl_balances` table keyed by `(entity_id, period_id, account_id, cost_centre_id,
currency_code)` holding period debit/credit movements, maintained **inside the
posting transaction**. A trial balance becomes a single indexed scan of a table
that grows with *accounts × periods × cost centres*, not with transactions. This is
the single highest-leverage performance decision in the system, and it must be
designed in from Phase 1 — bolting it on later means rebuilding every report.
Guard it with a nightly job that recomputes balances from `journal_lines` and
alerts on any drift (this also catches posting bugs).

**2. Index for the queries you will actually run.**
```sql
CREATE INDEX jl_report_idx ON journal_lines (entity_id, account_id, cost_centre_id)
  INCLUDE (debit_amount, credit_amount) WHERE posted_at IS NOT NULL;
CREATE INDEX jl_entry_idx  ON journal_lines (journal_entry_id);
CREATE INDEX jl_date_brin  ON journal_lines USING brin (entry_date);
```
A BRIN index on `entry_date` costs almost nothing and works extremely well because
ledger data is naturally clustered by date — a rare case where BRIN beats B-tree.

**3. Partition `journal_lines` by fiscal year** once it exceeds ~50M rows
(PostgreSQL declarative range partitioning). Below that, do not bother; partitioning
buys little and complicates constraints. Design the primary key as
`(id, entry_date)` now so the future partition key is already available.

**4. Report concurrency.** Long reports run on the replica with
`SET TRANSACTION ISOLATION LEVEL REPEATABLE READ` for a consistent snapshot across
multiple statements — a P&L and a Balance Sheet in the same pack must be drawn
from the same instant.

**5. Close-period throughput.** The heavy month-end jobs (depreciation,
allocation, revaluation, accrual reversal) are queued, idempotent, and resumable,
keyed by `(period_id, run_type)` with a unique constraint so a double-click cannot
post depreciation twice. Idempotency here is a *correctness* requirement, not a
performance one.

**Rough capacity sanity check.** At A-10 volumes, `journal_lines` reaches ~5M rows
after a decade — comfortably a single-node workload on modest hardware. The design
above is sized for 10× that without re-architecture.

---

## 2.6 Security

Financial systems fail at authorisation far more often than at authentication.
Layered controls:

**Authentication**
- Filament panel with enforced 2FA for any user holding a posting or approval role.
- Password policy + lockout; SSO (Azure AD / Google Workspace) if Al-Idan has one.
- Sessions bound to IP-range where practical for finance staff; short idle timeout.

**Authorisation**
- RBAC via Laravel policies + Filament resource permissions. Suggested baseline roles:
  `viewer`, `ap_clerk`, `ar_clerk`, `cashier`, `gl_accountant`, `approver`,
  `financial_controller`, `auditor` (read-everything, write-nothing, including
  read of the audit trail), `system_admin` (no posting rights).
- **Segregation of duties enforced structurally**: the creator of an entry can never
  be its approver; vendor master changes and payment release are different
  permissions; bank reconciliation is separate from cash posting. Where headcount
  makes this impossible (A-06), the system logs an **SoD override** requiring a
  documented compensating control and surfaces those overrides in a report the
  auditor reads.
- Data-scope permissions: a cost-centre manager sees only their cost centres'
  transactions. Enforced in the same place as entity scoping.

**Data protection**
- TLS everywhere; PostgreSQL `scram-sha-256`, no trust auth, no superuser app role.
  *(Note: the currently scaffolded dev credentials use a shared `root` superuser —
  acceptable for local development, must not reach staging or production. Create a
  dedicated least-privilege role `alidan_app` owning only the application schema.)*
- Encryption at rest for the DB volume and for attachment storage.
- PII (employee, customer) handled under least privilege; payroll data visible only
  to the payroll role, including in the audit trail.
- Secrets in environment, never in the repository; `.env` excluded from git (already
  the case).

**Integrity & non-repudiation — the part unique to accounting**
- Posted entries are **append-only**. There is no UPDATE and no DELETE path in the
  application, and the database enforces it:
  ```sql
  CREATE RULE jl_no_delete AS ON DELETE TO journal_lines DO INSTEAD NOTHING;
  CREATE TRIGGER jl_no_update BEFORE UPDATE ON journal_lines
    FOR EACH ROW WHEN (OLD.posted_at IS NOT NULL) EXECUTE FUNCTION raise_immutable();
  ```
- Corrections are reversals (FR-030) carrying a mandatory reason code and a link to
  the entry being corrected.
- Audit trail written by database trigger, not application code, so it cannot be
  bypassed by a script, a console command or a future developer taking a shortcut.
- Optional hash-chaining of posted entries (each entry stores
  `SHA256(prev_hash || canonical_payload)`) gives tamper-evidence that an auditor
  can verify independently. Cheap to add at Phase 1; near-impossible to add
  retrospectively with any credibility.

**Operational**
- Every login, permission change, failed authorisation, export and report download
  logged with actor, IP and timestamp. Bulk exports of financial data are a
  reportable event.
- Periodic access review (quarterly) as a checklist item, not an intention.

---

## 2.7 Key architectural risks

| Risk | Consequence | Mitigation |
|---|---|---|
| Subledger keeps its own balances "for speed" | Irreconcilable ledgers; the ABX #3 failure mode | Single-writer rule; nightly balance-drift alert |
| Two posting bases (cash and accrual) | Permanent divergence | Accrual-native; cash basis is a report transformation only |
| Dimensions retrofitted after go-live | Expensive backfill, unreliable history | Add `cost_centre_id` in Phase 1 even if unused until Phase 4 — see [05 §5.9](05-cost-centres.md) |
| CoA designed by whoever builds it first | Rework of every report and every mapping | Freeze the CoA with the finance lead before Phase 1 exit |
| Filament resource logic drifting into business logic | Rules unenforced by API/import paths | All writes go through domain services; Filament is a UI over them |
