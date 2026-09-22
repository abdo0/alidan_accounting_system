# 05 — Cost Centre Feasibility & Design

**Reads from:** [01](01-source-analysis.md), [03](03-data-model.md), [04](04-transactions-workflows.md)
**Question asked:** whether and how to add cost centres, with trade-offs and effort.

> *Spelling note: this document set uses the British "cost centre" consistently,
> including in identifiers (`cost_centre_id`, `requires_cost_centre`). If you
> prefer "cost center" in code, decide now — it is a trivial rename today and an
> annoying one after Phase 1.*

---

## 5.1 Verdict up front

**Yes — add cost centres, and add the column in Phase 1 even though the feature
does not ship until Phase 4.**

Three reasons, in order of weight:

1. **The requirement is already in your source material.** Doc A states plainly:
   *"P&L Acc is prepared by each department; B/S is normally prepared for an
   overall operation."* A departmental P&L with no departmental dimension on the
   posting line is not implementable. This is FR-023, not a wish-list item.
2. **The cost of adding it now is close to zero; the cost of adding it later is
   not.** A nullable `bigint` column on `journal_lines` costs 8 bytes and one
   index. Retrofitting it after two years of postings costs a backfill exercise
   against history nobody can reliably reconstruct — see §5.9, where the retrofit
   estimate is roughly **2.5× the greenfield estimate**, and the *quality* of the
   result is permanently worse because the historical dimension values are
   inferred rather than captured.
3. **Without it, the management-reporting requests arrive anyway** and get met by
   exporting the GL to spreadsheets and tagging rows by hand. That is how
   organisations end up with two sets of numbers that disagree.

The risk is not technical. It is **operational**: cost centres only produce value
if someone owns the cost-centre structure and if data entry actually carries the
right value. §5.10 addresses that directly, because it is where this feature
usually fails.

---

## 5.2 Approach evaluation

Four ways to add a cost-centre concept. I evaluated each against the model in
[03](03-data-model.md).

### Option A — Account-code segmentation

Extend the account code with a cost-centre segment: `6500-CC30` = Rent, Sales Dept.

| | |
|---|---|
| Schema change | None to `journal_lines`; the CoA grows |
| Effort | Low upfront |
| **Account count** | natural accounts × cost centres. 200 × 40 = **8,000 accounts**, and that is before projects |
| Adding a cost centre | Create ~200 accounts, extend every report mapping, re-train everyone |
| Two dimensions at once (CC × project) | Impossible without a third segment → 320,000 accounts |
| Reporting | String-parsing the account code; fragile |
| Hierarchical rollup | Only by code-prefix convention; breaks the first time a code is reused |
| Fit with Doc A's "account titles describe what is recorded" | Poor — the title must encode nature *and* place |

**Rejected.** This is the 1980s general-ledger design, and it is rejected for the
same reason it was abandoned: combinatorial explosion, with the added defect that
closing a cost centre leaves hundreds of dead accounts that nobody dares delete
because they carry history.

### Option B — Dimension column on the posting line ✅

`journal_lines.cost_centre_id bigint REFERENCES cost_centres(id)`.

| | |
|---|---|
| Schema change | One nullable FK column, one hierarchy table, one index |
| Account count | Unchanged (~200) |
| Adding a cost centre | `INSERT` one row |
| Two dimensions at once | `GROUP BY cost_centre_id, project_id` |
| Reporting | Native aggregation; rolls up through the `ltree` path |
| Validation | Referential integrity, active/effective-date checks, per-account requirement flag |
| Consolidation | Cost centres can be group-shared or entity-local |
| Performance | See §5.7 — with `gl_balances` keyed by cost centre, a cost-centre P&L costs the same as a company P&L |

**Recommended.** This is what every credible modern ledger does (Xero tracking
categories, QuickBooks classes, NetSuite/Oracle segments, SAP cost centres,
Odoo analytic accounts), for the reasons above.

### Option C — Free tagging (polymorphic labels)

A `taggables` table letting any transaction carry arbitrary string tags.

| | |
|---|---|
| Effort | Very low |
| Referential integrity | None — "Sales", "sales", "Sales Dept" all coexist |
| Validation | Cannot require a tag per account; cannot prevent two cost-centre tags on one line |
| Rollup | None |
| Budget comparison | Not possible against a free-text key |
| Auditability | A tag can be edited after posting, which silently rewrites history |

**Rejected for financial dimensions.** Tagging is fine for informal annotation
(e.g. marking transactions for a one-off review). It is not a basis for reporting
that anyone will sign.

### Option D — Parallel analytic ledger (Odoo-style)

A separate `analytic_lines` table, many-to-one with GL lines, allowing one GL line
to be split across several cost centres by percentage without unbalancing the GL.

| | |
|---|---|
| Strength | Splits an expense across cost centres without extra GL lines; supports management-only allocations that must not touch statutory books |
| Weakness | **Two sources of truth.** Analytic total can drift from GL total, and reconciling them becomes a monthly chore |
| Weakness | Doubles write volume and adds a second immutability regime |
| Complexity | Materially higher — a second posting engine with its own validation |

**Rejected as the primary mechanism, adopted selectively.** The split-cost problem
it solves is real but is better solved by **splitting the GL line itself** (§5.5):
one line per cost centre, still balanced, still one source of truth. A separate
analytic layer is justified only for *secondary* allocations that must not appear
in statutory books, and §5.6 handles those with a memo/statistical journal instead
— same benefit, far less machinery.

### Decision matrix

| Criterion (weight) | A: Segmented | **B: Column** | C: Tags | D: Analytic |
|---|---|---|---|---|
| Correctness & integrity (5) | 3 | **5** | 1 | 4 |
| Reporting power (5) | 2 | **5** | 1 | 5 |
| Maintenance cost (4) | 1 | **5** | 4 | 2 |
| Performance (3) | 3 | **5** | 2 | 3 |
| Implementation effort (3) | 4 | **4** | 5 | 2 |
| Migration/back-compat (4) | 2 | **5** | 3 | 3 |
| Audit & consolidation (4) | 3 | **5** | 1 | 3 |
| **Weighted total /140** | 66 | **135** | 50 | 91 |

---

## 5.3 Cost centre structure

Four types, because they behave differently in allocation and reporting:

| Type | Meaning | Receives revenue? | Allocated out? |
|---|---|---|---|
| `operating` | Earns revenue (Sales, Service, a branch) | Yes | No |
| `support` | Serves other centres (IT, HR, Finance, Facilities) | No | **Yes** |
| `project` | Time-boxed, often mirrors a `projects` row | Sometimes | No |
| `admin` | General overhead not sensibly allocated | No | Optionally |
| `statistical` | Holds drivers only (headcount, m²), never money | No | n/a |

Suggested starting hierarchy — keep it shallow; three levels is almost always enough:

```
AL-IDAN (root, not postable)
├── 100 OPERATIONS                     (not postable)
│   ├── 110 Baghdad Branch             operating
│   ├── 120 Basra Branch               operating
│   └── 130 Erbil Branch               operating
├── 200 COMMERCIAL                     (not postable)
│   ├── 210 Sales                      operating
│   └── 220 Marketing                  support
├── 300 SUPPORT                        (not postable)
│   ├── 310 Finance                    support
│   ├── 320 IT                         support
│   ├── 330 HR                         support
│   └── 340 Facilities                 support
├── 400 PROJECTS                       (not postable)
│   └── 4xx per project                project
└── 900 UNASSIGNED                     admin   ← migration & transition landing zone
```

Rules:
- **Only leaves are postable.** Parents exist for rollup.
- **Never delete a cost centre.** Close it with `effective_to`; history must keep
  resolving. A closed centre stops being selectable but keeps reporting.
- **Codes are never reused.** Reissuing code 210 to a different department three
  years later silently corrupts every trend report.
- Depth ≥ 4 is a warning sign — it usually means a second dimension (project,
  product line) is being smuggled into the cost-centre tree.

---

## 5.4 Schema changes — the complete set

Everything needed, in one place. This is the concrete answer to "what database
changes does this require".

```sql
-- 1) The dimension table (full definition in 03 §3.5)
CREATE TABLE cost_centres ( … );          -- + ltree path, GiST index

-- 2) The posting dimension — THE critical column
ALTER TABLE journal_lines
    ADD COLUMN cost_centre_id bigint REFERENCES cost_centres(id);

CREATE INDEX jl_cost_centre_idx ON journal_lines (entity_id, cost_centre_id, entry_date)
    INCLUDE (account_id, debit_amount, credit_amount)
    WHERE posted_at IS NOT NULL;

-- 3) Per-account enforcement policy
ALTER TABLE accounts
    ADD COLUMN requires_cost_centre boolean NOT NULL DEFAULT false,
    ADD COLUMN default_cost_centre_id bigint REFERENCES cost_centres(id),
    ADD COLUMN cost_centre_enforced_from date;   -- phased enforcement, §5.9

-- 4) Balance store keyed by cost centre (03 §3.6) — already in the unique key

-- 5) Defaulting sources, so users rarely type it
ALTER TABLE customers      ADD COLUMN default_cost_centre_id bigint REFERENCES cost_centres(id);
ALTER TABLE vendors        ADD COLUMN default_cost_centre_id bigint REFERENCES cost_centres(id);
ALTER TABLE items          ADD COLUMN default_cost_centre_id bigint REFERENCES cost_centres(id);
ALTER TABLE fixed_assets   ADD COLUMN cost_centre_id bigint REFERENCES cost_centres(id);
ALTER TABLE employees      ADD COLUMN cost_centre_id bigint REFERENCES cost_centres(id);
ALTER TABLE bank_accounts  ADD COLUMN default_cost_centre_id bigint REFERENCES cost_centres(id);
ALTER TABLE users          ADD COLUMN default_cost_centre_id bigint REFERENCES cost_centres(id);

-- 6) Document-level dimensions (header default, line override)
ALTER TABLE sales_invoices      ADD COLUMN cost_centre_id bigint REFERENCES cost_centres(id);
ALTER TABLE sales_invoice_lines ADD COLUMN cost_centre_id bigint REFERENCES cost_centres(id);
ALTER TABLE vendor_bills        ADD COLUMN cost_centre_id bigint REFERENCES cost_centres(id);
ALTER TABLE vendor_bill_lines   ADD COLUMN cost_centre_id bigint REFERENCES cost_centres(id);

-- 7) Access scoping: who may see / post to which centres
CREATE TABLE cost_centre_user (
    cost_centre_id bigint NOT NULL REFERENCES cost_centres(id),
    user_id        bigint NOT NULL REFERENCES users(id),
    access_level   varchar(20) NOT NULL DEFAULT 'view',  -- view | post | manage
    PRIMARY KEY (cost_centre_id, user_id)
);

-- 8) Budgets by cost centre (FR-037)
CREATE TABLE budgets (
    id bigserial PRIMARY KEY,
    entity_id bigint NOT NULL REFERENCES entities(id),
    fiscal_year_id bigint NOT NULL REFERENCES fiscal_years(id),
    name varchar(120) NOT NULL,
    version smallint NOT NULL DEFAULT 1,
    status varchar(20) NOT NULL DEFAULT 'draft',   -- draft | approved | archived
    approved_by bigint REFERENCES users(id), approved_at timestamptz,
    UNIQUE (entity_id, fiscal_year_id, name, version)
);
CREATE TABLE budget_lines (
    id bigserial PRIMARY KEY,
    budget_id bigint NOT NULL REFERENCES budgets(id) ON DELETE CASCADE,
    account_id bigint NOT NULL REFERENCES accounts(id),
    cost_centre_id bigint REFERENCES cost_centres(id),
    project_id bigint REFERENCES projects(id),
    fiscal_period_id bigint NOT NULL REFERENCES fiscal_periods(id),
    amount numeric(20,4) NOT NULL,
    UNIQUE NULLS NOT DISTINCT
        (budget_id, account_id, cost_centre_id, project_id, fiscal_period_id)
);

-- 9) Allocation engine (§5.6)
CREATE TABLE allocation_drivers (
    id bigserial PRIMARY KEY, code varchar(30) NOT NULL UNIQUE,
    name varchar(120) NOT NULL,
    driver_type varchar(30) NOT NULL,  -- headcount|floor_area|revenue|direct_cost|
                                       -- transaction_count|machine_hours|fixed_percentage
    uom varchar(20)
);
CREATE TABLE allocation_driver_values (
    id bigserial PRIMARY KEY,
    allocation_driver_id bigint NOT NULL REFERENCES allocation_drivers(id),
    cost_centre_id bigint NOT NULL REFERENCES cost_centres(id),
    fiscal_period_id bigint NOT NULL REFERENCES fiscal_periods(id),
    value numeric(20,4) NOT NULL CHECK (value >= 0),
    source varchar(20) NOT NULL DEFAULT 'manual',  -- manual | derived | imported
    UNIQUE (allocation_driver_id, cost_centre_id, fiscal_period_id)
);
CREATE TABLE allocation_rules (
    id bigserial PRIMARY KEY,
    entity_id bigint NOT NULL REFERENCES entities(id),
    code varchar(30) NOT NULL, name varchar(150) NOT NULL,
    sequence smallint NOT NULL,                 -- step-down order
    method varchar(20) NOT NULL,                -- direct | step_down | reciprocal | fixed_pct
    source_cost_centre_id bigint REFERENCES cost_centres(id),
    source_account_filter jsonb,                -- {"class":"expense","from":"6000","to":"7999"}
    allocation_driver_id bigint REFERENCES allocation_drivers(id),
    target_selector jsonb,                      -- {"types":["operating"],"path_under":"AL-IDAN.100"}
    offset_account_id bigint REFERENCES accounts(id),  -- recharge-out account
    target_account_id bigint REFERENCES accounts(id),  -- recharge-in account
    is_statutory boolean NOT NULL DEFAULT false,-- false = memo/management only
    is_active boolean NOT NULL DEFAULT true,
    UNIQUE (entity_id, code)
);
CREATE TABLE allocation_runs (
    id bigserial PRIMARY KEY,
    entity_id bigint NOT NULL REFERENCES entities(id),
    fiscal_period_id bigint NOT NULL REFERENCES fiscal_periods(id),
    status varchar(20) NOT NULL DEFAULT 'draft',   -- draft|posted|reversed
    journal_entry_id bigint REFERENCES journal_entries(id),
    run_by bigint NOT NULL REFERENCES users(id), run_at timestamptz NOT NULL DEFAULT now(),
    UNIQUE (entity_id, fiscal_period_id)           -- idempotent, one per period
);
CREATE TABLE allocation_run_lines (
    id bigserial PRIMARY KEY,
    allocation_run_id bigint NOT NULL REFERENCES allocation_runs(id),
    allocation_rule_id bigint NOT NULL REFERENCES allocation_rules(id),
    source_cost_centre_id bigint NOT NULL REFERENCES cost_centres(id),
    target_cost_centre_id bigint NOT NULL REFERENCES cost_centres(id),
    account_id bigint NOT NULL REFERENCES accounts(id),
    driver_value numeric(20,4), driver_share numeric(12,8),
    amount numeric(20,4) NOT NULL
);
```

**Total: 1 new column on the hot table, 8 supporting tables, 12 defaulting
columns.** That is a genuinely small footprint for the reporting capability it
unlocks — which is the core of the feasibility answer.

---

## 5.5 Posting logic

### Determining the cost centre — a defaulting cascade

Users should almost never have to choose. First non-null wins:

```
1. Explicit value on the journal/document LINE
2. Document HEADER value (invoice/bill-level)
3. Source master record:
      fixed asset → asset.cost_centre_id
      employee    → employee.cost_centre_id
      item        → item.default_cost_centre_id
      customer / vendor → default_cost_centre_id
4. Account default (accounts.default_cost_centre_id)
5. Entering user's default (users.default_cost_centre_id)
6. NULL → validation decides (V-07): block, warn, or accept per §5.9 phase
```

Implemented as a single resolver so every path — UI, import, API, integration —
behaves identically:

```php
final class CostCentreResolver
{
    public function resolve(JournalLineDraft $line, JournalEntryDraft $entry): ?int
    {
        return $line->costCentreId
            ?? $entry->costCentreId
            ?? $this->fromSourceMaster($line)
            ?? $line->account()->default_cost_centre_id
            ?? $entry->createdBy()->default_cost_centre_id;
    }
}
```

### Which accounts require a cost centre

| Account class | Policy | Why |
|---|---|---|
| Revenue (4000s) | **Required** for operating centres | Departmental P&L needs both sides (Doc A, FR-023) |
| Cost of sales (5000s) | **Required** | |
| Operating expenses (6000–7999) | **Required** | The core use case |
| Other income/expense, finance, FX (8000s) | Optional — usually a corporate centre | Rarely attributable |
| Tax (9000s) | Optional, entity-level | |
| Assets (1000s) | Optional; **required** for fixed assets so depreciation inherits it | |
| Liabilities (2000s) | Not required | |
| Equity (3000s) | **Never** | Equity is not departmental |
| Control accounts (AR/AP/Inventory) | **Never** — dimension belongs on the revenue/expense side | Keeps the control account reconcilable in one dimension |
| Clearing/suspense (95xx) | Not required (must be zero at close anyway) | |

That last-but-one row matters: putting cost centres on the AR control account
means the AR subledger must reconcile *per cost centre*, which is a reconciliation
nobody wants and which breaks when a customer's payment covers invoices from two
departments.

### Splitting a cost across centres

The rent invoice of 3,000,000 shared between three branches by floor area
(50/30/20). **Split at the line level — one GL line per cost centre:**

```
Dr 6500 Rent  CC110 Baghdad   1,500,000
Dr 6500 Rent  CC120 Basra       900,000
Dr 6500 Rent  CC130 Erbil       600,000
    Cr 2000 Trade Payables              3,000,000
```

Still one balanced entry, one source of truth, no parallel ledger (which is why
Option D was rejected). The UI offers a "split by driver" action that expands one
entered line into N lines using an allocation driver, with the rounding residue
placed on the largest share so the total is exact.

### Statement scope

Cost centres apply to the **P&L only**, per Doc A ("B/S is normally prepared for
an overall operation"). A balance-sheet-by-cost-centre requires a full balanced
sub-book per centre — every cash movement, every receivable attributed — which is
an order of magnitude more work and is very rarely worth it. If divisional balance
sheets are genuinely needed, the right answer is **separate legal entities or
divisions** ([02 §2.3](02-architecture.md)), not a dimension.

---

## 5.6 Allocation methods

Support all four; they differ in accuracy and in how much argument they cause.

### 1. Direct allocation
Support-centre costs charged straight to operating centres by a driver. Ignores
support-to-support consumption. Simple, and where most organisations should start.

### 2. Step-down (sequential)
Support centres allocated in a defined order (`allocation_rules.sequence`); once
allocated, a centre receives nothing further. Recognises one-directional support
consumption. **Recommended default** — materially better than direct, and still
explainable to a non-accountant, which matters more than it sounds.

### 3. Reciprocal (simultaneous equations)
Fully recognises mutual support (IT serves HR, HR serves IT). Solve
`x = d + A·x` → `x = (I − A)⁻¹·d` by Gaussian elimination over a matrix of
support-centre count (typically ≤10 — trivially fast). Most accurate, and the
hardest to explain to the manager whose cost went up.

### 4. Fixed percentage
Manually agreed splits. Use where a driver is unavailable or politically contested.

### Driver sources

| Driver | Typical use | Source |
|---|---|---|
| Headcount | HR, canteen, general admin | HR system or manual, per period |
| Floor area (m²) | Rent, utilities, cleaning, security | Facilities, rarely changes |
| Revenue | Corporate overhead | **Derived from GL** — no manual entry |
| Direct cost | Management overhead | Derived from GL |
| Transaction count | Finance, AP processing | Derived from document counts |
| Machine/labour hours | Production support | Operations |
| Fixed % | Negotiated | Manual |

Drivers derived from the GL are recomputed each run, which avoids the standard
failure of allocations silently running on last year's headcount because nobody
updated the driver table.

### Worked example — step-down

Support: IT 40,000,000 · HR 24,000,000. Operating: Baghdad, Basra, Erbil.
Drivers: headcount HR-basis IT 8, Baghdad 30, Basra 18, Erbil 12;
IT-basis (workstations) HR 8, Baghdad 25, Basra 15, Erbil 10.

**Step 1 — IT allocated first** (58 workstations excluding IT itself):

| Target | Driver | Share | Amount |
|---|---|---|---|
| HR | 8 | 13.79% | 5,517,241 |
| Baghdad | 25 | 43.10% | 17,241,379 |
| Basra | 15 | 25.86% | 10,344,828 |
| Erbil | 10 | 17.24% | 6,896,552 |
| | | | **40,000,000** |

**Step 2 — HR allocated** (24,000,000 + 5,517,241 = 29,517,241; headcount 60,
IT excluded as already allocated):

| Target | Driver | Share | Amount |
|---|---|---|---|
| Baghdad | 30 | 50.00% | 14,758,621 |
| Basra | 18 | 30.00% | 8,855,172 |
| Erbil | 12 | 20.00% | 5,903,448 |
| | | | **29,517,241** |

**Resulting journal** (allocation journal `ALC`, `is_adjusting = false`):

```
Dr 7900 Allocated Overhead   CC110 Baghdad   32,000,000
Dr 7900 Allocated Overhead   CC120 Basra     19,200,000
Dr 7900 Allocated Overhead   CC130 Erbil     12,800,000
    Cr 7910 Overhead Recharged Out  CC320 IT        40,000,000
    Cr 7910 Overhead Recharged Out  CC330 HR        24,000,000
```

Note the design: **7900 and 7910 are a reciprocal pair that nets to zero at entity
level.** The entity P&L is unchanged; only the cost-centre view moves. This is what
lets the allocation be posted to the real ledger without distorting statutory
figures, and it removes the need for a parallel analytic ledger entirely.

Allocation runs are reversible as a unit (`allocation_runs.status = 'reversed'`),
because the first two months of any allocation policy always produce an argument
that ends in "re-run it with different drivers".

---

## 5.7 Performance impact

Honest assessment: **negligible on writes, strongly positive on reads** — provided
`gl_balances` is keyed by cost centre from day one.

**Storage.** One `bigint` per line = 8 bytes (PostgreSQL will usually absorb it in
existing row padding). At A-10 volumes (500k lines/year), 10 years ≈ 5M lines ≈
**40 MB**. Irrelevant.

**Writes.** One extra FK check per line. The referenced table has ≤200 rows and
will be entirely in cache. Measurable cost ≈ 0. The `gl_balances` UPSERT does gain
cardinality — the balance table grows by a factor of *distinct cost centres per
account* rather than transactions, typically 5–20× a table that was small to begin
with. At 200 accounts × 13 periods × 40 cost centres × 2 currencies ≈ **208,000
rows/year**, which is nothing.

**Reads.** This is where it pays:

| Query | Without balance store | With `gl_balances` keyed by CC |
|---|---|---|
| Entity trial balance | Aggregate over all lines (O(n) in ledger size) | Index scan, ~200 rows |
| Cost-centre P&L, one centre | Full scan + filter | Index scan, ~150 rows |
| All-centre P&L matrix | Scan + pivot | Single scan, ~6,000 rows |
| CC rollup (branch + children) | Recursive CTE + scan | `path <@ 'X'` on GiST + index scan |

Concrete: on a 5M-line ledger, a monthly cost-centre P&L from raw
`journal_lines` is a ~2–6 second aggregate; from `gl_balances` it is **single-digit
milliseconds**. Users run this report constantly.

**Cardinality warning.** The one real risk is cost-centre proliferation. If
someone creates a cost centre per *customer* or per *invoice*, `gl_balances` grows
to millions of rows and the benefit inverts. Mitigation: a governance rule (§5.10)
capping the count, plus a monitoring alert above 500 active centres. Per-customer
or per-job analysis belongs on the **project** dimension, which is designed for
high cardinality and is not keyed into the balance store by default.

**Index strategy.** The partial index in §5.4 covers the dominant query pattern
`WHERE entity_id = ? AND cost_centre_id IN (…) AND entry_date BETWEEN ? AND ?`,
and `INCLUDE (account_id, debit_amount, credit_amount)` makes GL-detail drill-down
index-only.

---

## 5.8 Reporting & consolidation

Unlocked by the dimension:

| Report | Description |
|---|---|
| **Cost centre P&L** | Full P&L for one centre, with or without allocated overhead — show both; managers are accountable for direct cost and only *informed* of allocated |
| **Cost centre matrix P&L** | Accounts down, cost centres across, entity total right — the primary management report |
| **Budget vs actual by cost centre** | Actual, budget, variance, variance %, YTD and full-year forecast (FR-037) |
| **Contribution report** | Revenue − direct cost by operating centre, before overhead |
| **Allocation statement** | Per support centre: what was allocated, on what driver, to whom — the transparency that stops allocation disputes |
| **Cost centre trend** | Rolling 12–24 months, one centre or comparative |
| **Manager dashboard** | Scoped by `cost_centre_user`; a manager sees only their own tree |
| **Unassigned report** | P&L postings with no cost centre — must trend to zero; a close gate in [04 §4.5](04-transactions-workflows.md) |
| **Departmental P&L** | Doc A's explicit requirement, satisfied by rollup to the department attribute |

**Consolidation.** Cost centres can be group-shared (`cost_centres.entity_id IS
NULL`) or entity-local. Recommendation: **share the top two levels across the
group, let entities own their leaves.** That gives comparable group-level
functional reporting ("total IT cost across all entities") without forcing every
entity into an identical structure. Intercompany eliminations post to a dedicated
`ELIM` cost centre so consolidated cost-centre reports remain explainable.

---

## 5.9 Backwards compatibility & migration

The column is **nullable**, so nothing existing breaks. Enforcement is phased, and
this staging is the crux of making the feature land without a revolt:

| Phase | Duration | Behaviour |
|---|---|---|
| **0 — Dormant** | From Phase 1 build | Column exists, no UI, always NULL. Zero user impact. |
| **1 — Optional** | 1 month | Field visible, defaults applied, never required. Users acclimatise. |
| **2 — Warn** | 1 month | Posting a required-CC account without one raises a warning and lands in `900 UNASSIGNED`. The unassigned report goes to the controller weekly. |
| **3 — Enforced (P&L)** | ongoing | `requires_cost_centre = true` on all 4000–7999 accounts; `cost_centre_enforced_from` set; posting blocked without a value. |
| **4 — Enforced (extended)** | optional | Extend to fixed assets and selected balance-sheet accounts. |

`accounts.cost_centre_enforced_from` is what makes this safe: enforcement is
**date-based**, so back-dated corrections to pre-enforcement periods still post,
while current-period entries are held to the new standard. Without that column,
turning enforcement on breaks every prior-period adjustment on the same day.

### Backfilling history

If there is history to backfill (a migrated ledger, or Phase 0–2 postings):

1. **Derive where reliable, in this order**
   - Fixed-asset depreciation → the asset's cost centre (100% reliable)
   - Payroll → the employee's cost centre (reliable)
   - Invoices/bills → the customer's/vendor's default (reasonably reliable)
   - Branch-specific accounts or bank accounts → that branch (reliable)
   - Anything else → `900 UNASSIGNED`
2. **Never guess.** A wrong cost centre is worse than a visibly absent one, because
   it produces a report that looks complete and is wrong. `UNASSIGNED` is honest.
3. Run the backfill as a **dated, reversible correction batch**, not an `UPDATE` on
   posted lines — posted lines are immutable ([02 §2.6](02-architecture.md)). For
   the pre-go-live migration window this can be a direct update of not-yet-posted
   data; after go-live it must be reclassification entries.
4. Publish a **coverage report** (% of P&L value carrying a cost centre, by month)
   so everyone knows how much of the history is trustworthy. Comparative reporting
   should start only from the first fully-covered period.

### Reclassification after posting

```
Dr 6500 Rent  CC120 Basra      900,000     (correct centre)
    Cr 6500 Rent  CC900 UNASSIGNED  900,000  (reverse the wrong one)
```
Same account, same entity total, only the dimension moves — a two-line entry in
the `GJ` journal with reason code `dimension_correction`, fully audited.

---

## 5.10 Where this feature actually fails (and how to prevent it)

The technical risk here is low. The failure modes are organisational, and they are
worth naming because they are what determines whether this is money well spent:

| Failure | Symptom | Prevention |
|---|---|---|
| **No owner** | Structure drifts, duplicates appear, nobody decides | Name one owner (the financial controller). Cost-centre create/close is an approved document ([04 §4.4](04-transactions-workflows.md)) |
| **Proliferation** | 400 cost centres, most with trivial balances | Governance rule: a centre needs a named manager and a budget; alert above 500; use `projects` for high-cardinality analysis |
| **Reorganisation** | Departments merge; trend reports break | Never delete or reuse codes; add a `reporting_group` mapping layer for restated comparatives |
| **Garbage entry** | Everything lands in UNASSIGNED | Defaulting cascade (§5.5) so users rarely choose; weekly unassigned report; enforcement phase 3 |
| **Allocation disputes** | Managers reject the numbers | Publish the allocation statement; report direct cost separately from allocated; agree drivers *before* go-live, in writing |
| **Reports nobody reads** | Feature used once at go-live | Tie cost-centre P&L to an actual management ritual — a monthly review meeting with the budget variance in front of it |

Of these, **agreeing the allocation drivers before go-live** is the one I would
insist on. Allocation methodology is a political decision dressed as a technical
one, and settling it during UAT is far cheaper than settling it in the first month
of live reporting.

---

## 5.11 Effort estimate

Assumes one senior Laravel/Filament developer familiar with the codebase, plus
finance-lead availability for structure and driver decisions. Ranges are
optimistic–likely–pessimistic in developer-days.

### If built with the system (recommended — Phase 1 column, Phase 4 feature)

| Work item | O | L | P |
|---|---|---|---|
| `cost_centres` table, ltree hierarchy, model, factory | 2 | 3 | 4 |
| Column + indexes + `gl_balances` key (done in Phase 1) | 0.5 | 1 | 2 |
| Filament resource: tree UI, CRUD, close/reopen, validation | 3 | 4 | 6 |
| Defaulting cascade + resolver + the 12 default columns | 2 | 3 | 5 |
| Posting-engine validation (V-07/V-08) + phased enforcement | 2 | 3 | 4 |
| UI: dimension pickers across JE, invoice, bill, asset, payroll | 3 | 5 | 7 |
| Split-by-driver line expansion UI | 2 | 3 | 4 |
| Access scoping (`cost_centre_user`, policies, manager view) | 2 | 3 | 5 |
| Reporting: CC P&L, matrix, trend, unassigned, contribution | 5 | 8 | 12 |
| Budgets: schema, import, approval, variance report | 4 | 6 | 9 |
| Allocation engine: rules, drivers, direct + step-down, run/reverse | 6 | 9 | 14 |
| Allocation: reciprocal method | 2 | 3 | 5 |
| Allocation console UI + allocation statement report | 3 | 4 | 6 |
| Consolidation handling + ELIM centre | 2 | 3 | 5 |
| Tests (unit, posting invariants, allocation property tests) | 5 | 7 | 10 |
| Data migration/backfill tooling + coverage report | 2 | 3 | 5 |
| Documentation + training material | 2 | 3 | 4 |
| **Total developer-days** | **47.5** | **71** | **107** |
| **Calendar (1 dev, ~4 productive days/week)** | **~12 wks** | **~18 wks** | **~27 wks** |

Plus non-development effort, which is routinely underestimated:
- Finance workshops to define the structure and drivers: **3–5 days** of the
  controller's time, spread over 3 weeks.
- UAT and parallel reporting: **5–8 days** finance-team time.
- Training: **1 day** per user group.

**A useful sub-scope:** items 1–10 (cost centres + reporting, no budgets, no
allocation) total **O 24.5 / L 36 / P 56 days ≈ 6–9 weeks** and deliver most of
the value. Budgets and allocation can follow a quarter later — and delaying
allocation gives you a quarter of real cost-centre data with which to argue about
drivers, which is a better position than arguing in the abstract.

### If retrofitted after 2 years live

| Additional work | L (days) |
|---|---|
| Everything above | 71 |
| Historical backfill: derivation rules, dry runs, reconciliation | 10 |
| Reclassification entries for posted history (immutability-safe) | 6 |
| `gl_balances` rebuild + verification across all prior periods | 5 |
| Re-testing every existing report against the new dimension | 8 |
| Change management: retraining, re-cutting comparatives | 7 |
| Contingency for discovered data quality problems | 10 |
| **Total** | **117 days ≈ 29 weeks** |

**≈ 1.65× the cost, and the historical data quality is permanently inferior.**
Extending this to a system with 5+ years of history, the multiple approaches 2.5×
and the backfill becomes largely guesswork. This asymmetry is the whole argument
for adding the column in Phase 1.

---

## 5.12 Recommendation

1. **Add `cost_centre_id` to `journal_lines` and the `gl_balances` key in Phase 1.**
   Cost: ~1 day. This is the decision that cannot be cheaply reversed.
2. **Ship the feature in Phase 4** (cost centres + reporting first, ~6–9 weeks),
   with budgets and allocation following in Phase 4b.
3. **Use Option B** — dimension column on the posting line. Reject account
   segmentation and free tagging outright; adopt a memo journal (§5.6) rather than
   a parallel analytic ledger for management-only allocations.
4. **Enforce in phases** (dormant → optional → warn → enforced), using
   `cost_centre_enforced_from` so back-dated corrections keep working.
5. **Settle governance before code**: one owner, ≤3 hierarchy levels, codes never
   reused, drivers agreed in writing.
6. **Keep cost centres off the balance sheet.** If divisional balance sheets are
   genuinely required, that is an entity-structure question, not a dimension one.
