# 08 — Implementation Plan

**Reads from:** all preceding documents
**Team assumed:** 1 tech lead / senior Laravel dev, 1 mid dev, 0.5 QA, 0.3 BA,
plus the financial controller as product owner (~1 day/week, more at close cycles).
Adjust every estimate proportionally if the team differs.

---

## 8.1 Minimal viable scope

The MVP is the smallest system that can **replace a manual ledger for one legal
entity**, and nothing more:

- One entity, one currency, calendar fiscal year with 12 periods + adjustment period
- Chart of accounts (§[03 §3.1](03-data-model.md)) with import and maintenance
- Six books of prime entry seeded (FR-005)
- Manual journal entry: draft → approve → post, immutable, reversal-only correction
- The posting engine with the full V-01…V-18 validation set
- `gl_balances` maintained at post
- **`cost_centre_id` column present and populated where known — UI dormant** ([05 §5.9](05-cost-centres.md))
- Attachments as posting evidence (P10)
- Trial balance, P&L, Balance Sheet, GL detail
- Period close with the checklist gates that apply at this scope
- Year-end close to retained earnings
- Users, roles, SoD, audit trail, auditor role
- Opening balance import

Explicitly **not** MVP: AR/AP subledgers, multicurrency, tax, inventory, payroll,
fixed assets, allocations, budgets, consolidation. Each is a later phase.

If the MVP cannot produce a trial balance that a qualified accountant will sign,
nothing later matters — so the MVP exit criterion is exactly that.

---

## 8.2 Phased roadmap

Durations are elapsed weeks for the team above, with the range reflecting
low/high confidence. Phases 2–3 and 5–6 can overlap with a second developer.

| Phase | Scope | Weeks | Key exit criterion |
|---|---|---|---|
| **0 — Foundations** | Entities, users, RBAC, audit triggers, fiscal calendar, CoA, currencies, numbering, attachments, Filament shell, CI | 3–5 | An auditor role can view an empty, fully-audited system |
| **1 — General Ledger (MVP)** | Journals, JE lifecycle, posting engine, `gl_balances`, TB/P&L/BS, period + year close, opening balances. **Cost centre column added here.** | 6–9 | Accountant signs a trial balance from real data |
| **2 — AR & AP** | Customers, vendors, invoices, bills, credit/debit notes, receipts, payments, allocations, **customer deposits (FR-027)**, ageing, statements, 3-way match, sequence gap report | 7–10 | Subledgers reconcile to control accounts with zero difference for a full month |
| **3 — Cash, bank & reporting depth** | Bank accounts, cash book, petty cash imprest, bank import (I-1), reconciliation, Statement of Cash Flows, KPI pack | 4–6 | A bank rec approved with zero difference; SCF proves to actual cash |
| **4a — Cost centres** | Dimension UI, defaulting cascade, enforcement phases, CC P&L, matrix, unassigned, access scoping | 6–9 | Cost-centre P&L for a full period reconciles to entity P&L |
| **4b — Budgets & allocations** | Budget import/approval, variance reporting, drivers, direct + step-down + reciprocal allocation, allocation statement | 5–8 | Allocation run posts, nets to zero at entity level, and is reversible |
| **5 — Fixed assets** | Register, categories, depreciation runs, disposals, revaluation, asset reports | 3–5 | Register reconciles to 1400s/1800s; depreciation posts by cost centre |
| **6 — Inventory** | Items, warehouses, stock moves, weighted-average valuation, internal use (FR-022), count adjustments | 5–8 | Inventory subledger reconciles; COGS formula reproduces Doc A exactly |
| **7 — Payroll & tax** | Employee master, pay runs, payslips, statutory deductions, GL posting, tax codes, determination, returns | 6–10 | Payroll control accounts clear to zero; a tax return reconciles to the ledger |
| **8 — Multi-entity, multicurrency, consolidation** | Additional entities, FX rates, revaluation, translation, intercompany, eliminations, consolidated statements | 6–9 | Consolidated statements balance; intercompany eliminates to zero |
| **9 — Hardening** | Performance, partitioning if needed, DR drill, penetration test, documentation, handover | 3–4 | Restore-from-backup drill passes; pen test findings closed |

**Total: 54–83 weeks ≈ 13–20 months.** For a smaller ambition — single entity,
GL + AR/AP + cash + cost centres only (phases 0–4a) — the range is
**26–39 weeks ≈ 6–9 months**, and that is the scope I would recommend committing
to first. Decide phases 5–8 once the core is live and the team has real usage data.

---

## 8.3 Data migration

### Strategy: opening balances + open items, not full history

Migrating years of transaction detail is where accounting implementations go to
die. The recommended cutover carries:

| Migrate | Why |
|---|---|
| Opening trial balance at cutover date | Non-negotiable |
| **Open** AR invoices (unpaid/partly paid), at document level | Needed for ageing and collection |
| **Open** AP bills, at document level | Same |
| Fixed asset register with cost and accumulated depreciation to date | Needed for future depreciation |
| Inventory quantities and values at cutover | Needed for COGS |
| Master data: customers, vendors, items, employees, CoA, cost centres | Needed for operation |
| Bank balances and unpresented items | Needed for the first reconciliation |
| Summary prior-year P&L and BS (one line per account per period) | Enables comparatives without detail |

**Do not migrate** closed transactions, historical journal detail, or paid
invoices. Keep the legacy system (or its export) read-only for enquiry and state
that explicitly in the cutover plan. If full history is genuinely required — and
it usually is not — treat it as a separate project with its own budget, because it
roughly doubles migration effort (A-11).

### Steps

| # | Step | Validation gate |
|---|---|---|
| 1 | Freeze the CoA; map legacy accounts → new accounts, 1:1 or many:1 | Every legacy account mapped; no unmapped balance |
| 2 | Define cost centres and the historical derivation rules ([05 §5.9](05-cost-centres.md)) | Coverage report agreed |
| 3 | Extract and cleanse master data | Duplicate customers/vendors merged; tax numbers validated |
| 4 | Load master data into a staging environment | Row counts match; spot-check 20 records per entity |
| 5 | Extract the cutover trial balance from the legacy system | Legacy TB balances |
| 6 | Load opening balances as an `opening` journal entry | Σ Dr = Σ Cr; new TB **equals** legacy TB, line by line |
| 7 | Load open AR/AP at document level | Subledger totals **equal** the migrated control-account balances |
| 8 | Load fixed assets | Register cost and accumulated depreciation equal the migrated 1400s/1800s |
| 9 | Load inventory | Valuation equals the migrated 1300 balance |
| 10 | Load prior-period summary for comparatives | Prior-year P&L agrees to signed statements |
| 11 | **Dry run the entire migration at least twice** | Second run reproduces the first exactly |
| 12 | Reconcile and obtain finance sign-off | Controller signs the migration reconciliation pack |
| 13 | Production cutover in a closed window | All gates 6–10 re-pass in production |
| 14 | Parallel run (§8.4) | Two close cycles agree |

Gate 6 is the one that matters: **the migrated trial balance must equal the legacy
trial balance exactly, account by account.** Not "within tolerance". Any
difference is a mapping error, and mapping errors compound forever.

---

## 8.4 Testing strategy

### Unit tests
- **Posting invariants are the priority.** Every rule V-01…V-18 gets a test that
  proves it *blocks* the bad case, not merely that the good case works.
- Property-based tests on the posting engine: for any randomly generated valid
  entry, Σ Dr = Σ Cr holds, `gl_balances` deltas equal the line amounts, and the
  TB still balances afterwards. This class of test finds the bugs that
  example-based tests miss.
- Money arithmetic: rounding, IQD zero-decimal handling, allocation residue.
- Depreciation methods against hand-computed schedules.
- Allocation algorithms, including the reciprocal solver against a worked matrix.
- FX: the [04 §4.7](04-transactions-workflows.md) worked example is a test case.

### Integration tests
- Full document lifecycles: invoice → receipt → allocation → ageing → GL.
- Period close with every gate, including forced failures for each gate.
- Year-end close and opening-balance roll, then prove last year's TB is unchanged.
- Migration dry run as an automated, repeatable suite.
- Concurrency: two users posting to the same account simultaneously; double-submit
  of the same entry; simultaneous close attempts.

### The tests that specifically matter here
- **Reconstruct Doc A's worked example** (Joan Miller, Jan 1–31) end to end:
  14 transactions, 6 adjusting entries, trial balance, P&L, balance sheet. If the
  system reproduces that textbook answer exactly, the core semantics are right.
  This is a genuinely good acceptance test because the expected output is
  published and independent of anyone's opinion.
- **Reproduce Doc A's cost-of-sales formula** including internally used items.
- **Reproduce all 15 ratios** from a known trial balance.

### UAT
- Run by the finance team, not developers, on production-like migrated data.
- Scripted scenarios per role, plus unscripted exploratory time.
- **A full month-end close performed by the finance team unaided** is the real UAT.
- Auditor walkthrough before sign-off — bring the external auditor in *before* go-live,
  not after; their objections are much cheaper to address in UAT.

### Parallel run
Two full close cycles with both systems. Compare TB, P&L and BS line by line;
every difference is investigated and either fixed or documented as a known,
accepted change in treatment. Go-live decision is taken after the second clean
cycle, not the first — the first always has surprises.

---

## 8.5 Training

| Audience | Content | Duration |
|---|---|---|
| AR/AP clerks | Their document flows, attachments, approvals | 0.5 day + a supervised week |
| Cashier | Receipts, deposits (FR-027 explicitly), petty cash, bank rec | 0.5 day |
| GL accountant | JE, adjustments, reconciliations, close checklist | 1 day |
| Controller | Everything + approvals, close, cost centres, allocations, exception reports | 2 days |
| Cost-centre managers | Reading their P&L, budget variance, what allocated cost means | 2 hours |
| Auditor / external | Read-only navigation, exports, audit trail | 2 hours |
| IT / admin | Backups, restores, user admin, monitoring, integration queues | 1 day |

Materials: role-based quick reference cards (one page, bilingual), a recorded
walkthrough per flow, and an in-system help panel on each Filament resource.
Budget **2–3 weeks of a BA's time** for materials — training content is routinely
forgotten in estimates and then produced badly at the last minute.

---

## 8.6 Risks

| # | Risk | P | I | Mitigation |
|---|---|---|---|---|
| R-01 | **The source documents are teaching material, so real requirements are still unknown** ([01 §1.1](01-source-analysis.md)) | High | High | Requirements workshop closing A-01/02/03/08/11/15 before Phase 1 freeze. **Do this first.** |
| R-02 | Chart of accounts changes after Phase 1 | Med | High | Freeze with the controller at Phase 1 exit; reserve code gaps; statutory mapping layer rather than CoA contortion |
| R-03 | Migration data quality worse than expected | High | High | Two dry runs; exact-match gates; budget 15% contingency |
| R-04 | Iraqi statutory requirements misunderstood (A-04) | Med | High | Engage a local tax adviser at Phase 0. **Do not rely on this document for Iraqi tax law.** |
| R-05 | Cost centres retrofitted later | Med | High | Add the column in Phase 1 — 1 day now vs ~46 extra days later ([05 §5.11](05-cost-centres.md)) |
| R-06 | Finance team availability below plan | High | High | Contract their time explicitly; slip the phase rather than skip UAT |
| R-07 | Scope creep into ERP territory (CRM, procurement, WMS) | High | Med | Phase gate discipline; integrate rather than build ([06](06-integrations-api.md)) |
| R-08 | Key-person dependency on one developer | Med | High | Two devs from Phase 1; documentation as a definition-of-done item; no solo knowledge of the posting engine |
| R-09 | Performance degradation at year 3+ | Low | Med | `gl_balances` from day 1; partition-ready PK; load test at 10× volume in Phase 9 |
| R-10 | Users bypass controls ("just let me post it") | Med | High | Overrides permitted but logged and reported; controller reviews monthly |
| R-11 | Parallel run abandoned early under time pressure | Med | High | Make two clean cycles a contractual go-live criterion |
| R-12 | Arabic/RTL rendering problems in statements (A-05) | Med | Med | Prototype an Arabic PDF statement in Phase 0, not Phase 7 |
| R-13 | Backups untested | Low | Critical | Quarterly restore drill; the existing `db-backup` pattern of keeping a *live restored copy* is the right model |
| R-14 | Allocation methodology disputed after go-live | High | Med | Agree drivers in writing during UAT ([05 §5.10](05-cost-centres.md)) |

---

## 8.7 Acceptance criteria

### Per phase

**Phase 0** — An auditor-role user can log in and see an empty but fully
configured system; every financial table has an audit trigger proven by test; CI
runs the full suite on every push.

**Phase 1 (MVP)** — A qualified accountant posts a month of real transactions,
runs a trial balance that balances, produces a P&L and Balance Sheet that they
sign, closes the period, and cannot post into it afterwards. A posted entry cannot
be edited or deleted by any route, including direct SQL as the application user.

**Phase 2** — AR and AP subledgers reconcile to their control accounts with zero
difference across a full month; a customer deposit received before delivery
appears correctly as a liability (ABX #1); the sequence gap report is clean.

**Phase 3** — A bank reconciliation is approved with zero difference; the Statement
of Cash Flows' closing cash equals the actual cash and bank balances; all 15 KPIs
compute.

**Phase 4a** — A cost-centre P&L for every centre sums exactly to the entity P&L;
unassigned P&L value is below 1% and trending down; a cost-centre manager sees only
their own tree.

**Phase 4b** — An allocation run posts, nets to zero at entity level, is fully
explained by the allocation statement, and can be reversed and re-run.

**Phases 5–8** — Each subledger reconciles to its control account; consolidated
statements balance and intercompany eliminates to zero.

### Overall go-live

1. Two consecutive parallel close cycles agree to the legacy system, with every
   difference explained and accepted in writing.
2. Migration reconciliation pack signed by the controller.
3. External auditor has walked the system and raised no blocking finding.
4. All Phase 1–4a acceptance criteria met.
5. A restore-from-backup drill completed successfully within the agreed RTO.
6. All users trained and holding the correct role; SoD matrix reviewed and approved.
7. No open critical or high defect.
8. Rollback plan documented and rehearsed.

---

## 8.8 Prioritised next actions

| # | Action | Owner | When |
|---|---|---|---|
| 1 | Requirements workshop to close assumptions A-01, A-02, A-03, A-08, A-11, A-15 | BA + controller | Week 1 |
| 2 | Confirm the Iraqi statutory regime and obtain the statutory code list (A-04) | Controller + tax adviser | Week 1–2 |
| 3 | Draft and freeze the chart of accounts against §[03 §3.1](03-data-model.md) | Controller | Week 2–4 |
| 4 | Draft the cost-centre structure and name its owner ([05 §5.3](05-cost-centres.md)) | Controller | Week 3–4 |
| 5 | Decide committed scope: phases 0–4a, or the full 0–9 | Sponsor | Week 4 |
| 6 | Set up environments, CI, and the audit-trigger framework | Tech lead | Week 1–3 |
| 7 | Prototype an Arabic/RTL statement PDF to de-risk R-12 | Dev | Week 3 |
| 8 | Begin Phase 0 | Team | Week 4 |

Action 1 is genuinely blocking for design decisions, but not for Phase 0
engineering work — items 6 and 7 can start immediately.
