# 01 — Source Analysis, Derived Requirements, Gaps & Assumptions

**Status:** Draft 1 · 2026-09-16 · Owner: Accounting Systems Architecture
**Inputs analysed:**
- `Financial Accounting.pdf` — 132 slides, 720×540pt PowerPoint, title *"A Business Accounting System"*, author `ymegaloeconomou`, created 2023-03-19.
- `THE_ACCOUNTING_SYSTEM.pdf` — 5 pages A4, © Mr S. Jugnarain (BSc, MBA, FCCA), created 2019-09-04.

---

## 1.1 What these documents actually are — read this before anything else

The brief described both files as describing "current accounting system
requirements/implementation". **They do not.** Both are *teaching material* on
financial-accounting fundamentals:

| | Doc A — `Financial Accounting.pdf` | Doc B — `THE_ACCOUNTING_SYSTEM.pdf` |
|---|---|---|
| Genre | University/college lecture deck | ACCA/FIA-style tutor handout |
| Audience | Students | Students |
| Domain flavour | Hospitality (references *china, glass, linen, uniforms*, "hospitality operations") | Generic trading business; worked example is a second-hand car dealer |
| Ends with | Ratio analysis formulas | An exam question ("Required: (a) Discuss the corrective steps…") |
| Names a software product? | No | No |
| Names Al-Idan, a legacy system, users, volumes? | No | No |

**Consequence for this design.** There is no as-is system documented, therefore
there is no as-is gap analysis possible from these inputs. What these documents
*do* give us is authoritative and genuinely useful: a complete, internally
consistent statement of the **accounting semantics the system must implement**,
and — in Doc B — an explicit **processing pipeline** that maps almost one-to-one
onto a system architecture. This document therefore does three things:

1. Extracts that semantic content as numbered, testable requirements (§1.3–1.4).
2. Lists what a production system needs that the sources are silent on (§1.5).
3. Records every assumption I have made to fill those silences (§1.6), each with
   a named way to confirm it.

Nothing in §1.6 should be treated as fact until confirmed. Several of them
(base currency, statutory chart of accounts, number of legal entities) change
the design materially if wrong.

---

## 1.2 Extraction — Doc B: the processing pipeline

Doc B's central diagram is the spine of any accounting system, and the module
boundaries in [02 — Architecture](02-architecture.md) follow it deliberately:

```
TRANSACTIONS            an event affecting the business, expressible in money
   │ evidenced by
SOURCE DOCUMENTS        invoice, credit note, receipt, voucher, cheque stub…
   │ entered into
BOOKS OF PRIME ENTRY    sales / purchases / returns day books, cash book, journal
   │ posted to
THE LEDGER              double-entry accounts (general + subsidiary)
   │ checked by
TRIAL BALANCE           Σ debits = Σ credits
   │ helps to prepare
FINANCIAL STATEMENTS    P&L, SOCE, Balance Sheet, Cash Flows
```

### Source documents enumerated by Doc B (all ten)

1. Sales invoices (credit sales) · 2. Purchase invoices (credit purchases) ·
3. Credit notes sent (returns inwards) · 4. Credit notes received (returns
outwards) · 5. Debit notes (undercharged debtors) · 6. Receipts issued (cash
received) · 7. Remittance advice issued (cheque payments) · 8. Cheque stubs /
counterfoils · 9. Payment vouchers (petty cash & other) · 10. Statements of
account (monthly outstanding balance).

Doc B is emphatic: *"In a well established enterprise all transactions are backed
by a source document to ensure a sound internal control system."* This is the
justification for making document evidence a **posting precondition**, not an
optional attachment — see [07 — Reporting & Controls](07-reporting-controls.md) §7.4.

### Books of prime entry enumerated by Doc B (all six)

Sales Day Book · Purchases Day Book · Returns Inwards Day Book · Returns Outwards
Day Book · Cash Book & Petty Cash Book · General Journal (everything else:
fixed-asset acquisition/disposal on credit, bad-debt write-off, drawings of
goods, error corrections).

Doc B notes entries are *"referenced by the serial number of the document (e.g.
invoice numbers) in order to facilitate verification"* and that the Cash Book is
*multi-column* (analysis columns incl. discounts allowed/received). Both details
survive into the data model as `journals.code`, `journal_entries.source_document_no`
and analysis-by-dimension respectively.

### The ABX Motors case — a free set of control requirements

Doc B closes with an auditor's report on a second-hand car dealer listing three
irregularities. Each is a control this system must make structurally impossible,
and I have traced each to a specific mechanism:

| Irregularity in Doc B | Root failure | Mechanism in this design |
|---|---|---|
| "Advance deposits made by clients … have not been recorded anywhere in the books because the cars have not yet been delivered, although receipts have been issued" | Cash received without a liability recognised; receipt issued outside the ledger | Receipt capture **always** posts (Dr Cash / Cr Customer Deposits — a liability); revenue recognition is a separate, later event on delivery. Unapplied-receipt and deposit-ageing reports are period-close blockers. [04 §4.3](04-transactions-workflows.md), [07 §7.2](07-reporting-controls.md) |
| "Several invoices on the purchase of cars are missing" | No document-sequence integrity | Gapless per-journal sequences with a **gap-detection report**; three-way match (PO↔GRN↔invoice); mandatory attachment before post. [07 §7.4](07-reporting-controls.md) |
| "Year-end balances contain many errors and omissions, having a material effect on profit" | No close discipline, no reconciliation | Mandatory period-close checklist with hard gates: subledger↔control-account reconciliation, bank reconciliation, suspense = 0, unposted-batch = 0, then period lock. [04 §4.5](04-transactions-workflows.md), [07 §7.5](07-reporting-controls.md) |

---

## 1.3 Extraction — Doc A: semantics, statements, and formulas

### Principles the system must enforce or respect

Doc A lists 11, Doc B lists 4 (overlapping). Consolidated, with the system
implication of each:

| # | Principle | Source | System implication |
|---|---|---|---|
| P1 | Business entity | A+B | Every posting carries `entity_id`; owner's personal transactions are out of scope or booked to Drawings. Hard multi-entity boundary. |
| P2 | Going concern | A | Long-lived assets held at depreciated cost, not fire-sale value → Fixed Assets module. |
| P3 | Money measurement | A+B | Only monetary amounts post. Non-monetary facts (headcount, m²) live as **statistical/driver data**, not GL — relevant to cost allocation drivers. |
| P4 | Historical cost | A+B | Amounts recorded at transaction cost; revaluation is an explicit, separately-authorised event. |
| P5 | Periodicity | A | Fiscal calendar as a first-class object: years, periods, open/closed state. |
| P6 | Full disclosure | A | Note/disclosure attachments on statements; subsequent-events log. |
| P7 | Consistency | A | Policy settings (inventory method, depreciation method) versioned with effective dates, changes audited. |
| P8 | Conservatism | A | Impairment, allowance for doubtful debts, NRV write-downs supported. |
| P9 | Materiality | A | Materiality thresholds configurable; e.g. expense low-value assets in year 1 rather than depreciate. |
| P10 | Objectivity | A | *"Some form of documentation must exist to support a transaction before entered"* → evidence required to post. |
| P11 | Matching / accrual | A | Accruals engine; the four adjustment types below. |
| P12 | Duality | A+B | Σ Dr = Σ Cr enforced as a database-level invariant, per entry **and** per entity **and** per currency. |

Doc A also distinguishes **cash vs accrual basis** ("small businesses use cash
basis; medium & large use accrual"). Design decision: the system is **accrual-native**;
a cash-basis view is produced as a *report-time* transformation, never as a second
posting mode. Two posting modes is the single most common source of irreconcilable
ledgers.

### Statements required

Doc A: Balance Sheet, Income Statement (P&L), Statement of Cash Flows, Statement
of Retained Earnings. Doc B adds Statement of Changes in Equity. Union = the four
primary statements plus SOCE. Doc A notes *"P&L is prepared by each department; B/S
is normally prepared for an overall operation"* — this is, in effect, a
**departmental/cost-centre P&L requirement stated in the source material**, and is
the strongest in-source justification for the cost centre feature evaluated in
[05](05-cost-centres.md).

Doc A also names the link between the two: *"The account called Retained Earnings."*
→ year-end close rolls P&L into a configured retained-earnings account.

### Cost of sales formula (verbatim from Doc A)

```
Cost of sales = Opening inventory + Purchases − Closing inventory − Internally used items
```
where *internally used items* = "used but not for sale: expired, lost, broken,
spoiled, given for free". This mandates a **periodic** inventory capability and an
explicit `internal_use` stock-movement reason distinct from sale. See
[03 §3.9](03-data-model.md).

### The four adjustment types (Doc A)

| Type | Name | Example from Doc A | Entry |
|---|---|---|---|
| 1 | Deferred expense (allocate recorded cost across periods) | Prepaid rent 800 → 400/month; prepaid insurance 480 → 40/month; supplies used; depreciation | Dr Expense / Cr Prepaid or Accum. Depreciation |
| 2 | Accrued expense (incurred, unrecorded) | Accrued wages 180 | Dr Expense / Cr Accrued Liability |
| 3 | Deferred revenue (received in advance) | Unearned art fees 1,000 → 400 earned | Dr Unearned Revenue / Cr Revenue |
| 4 | Accrued revenue (earned, unrecorded) | 200 earned not billed | Dr Receivable / Cr Revenue |

Doc A's two hard rules on adjustments, both of which become validation rules:
**(a)** every adjusting entry touches at least one balance-sheet and at least one
income-statement account; **(b)** *"adjusting entries never involve the Cash account."*

### Contra accounts

Accumulated Depreciation is presented as the canonical contra account — paired
with its related account, deducted to give carrying value. The CoA therefore needs
an explicit `contra_of_account_id` and a `normal_balance` that may oppose its type.

### Statement of Cash Flows

Doc A specifies indirect method as default ("the easiest & most commonly used"),
three activity classes (Operating / Investing / Financing), required inputs
(current-period P&L, B/S, retained earnings statement, plus prior-period B/S and
retained earnings), and a complete **movement→adjustment mapping table** which I
have lifted directly into the cash-flow report configuration in
[07 §7.1](07-reporting-controls.md).

### Ratio pack

Doc A specifies exactly 15 ratios across four groups (liquidity, profitability,
long-term solvency, cash-flow adequacy). These are reproduced as the built-in KPI
set in [07 §7.3](07-reporting-controls.md) — they are a requirement, not a
nice-to-have, since the source treats them as standard output.

---

## 1.4 Derived functional requirements

Testable requirements extracted from the two documents. `Src` = A (Doc A), B (Doc B),
A+B (both). Requirements introduced by me to make a production system viable are
marked `Derived` and are traceable to an assumption in §1.6.

| ID | Requirement | Src | Priority |
|---|---|---|---|
| FR-001 | Maintain a chart of accounts, numbered, with account titles that describe what is recorded | A | MVP |
| FR-002 | Record every transaction as a balanced double entry (≥1 Dr, ≥1 Cr, Σ equal) | A+B | MVP |
| FR-003 | Support compound entries (>2 lines) | A | MVP |
| FR-004 | Capture entries in chronological order in a journal (book of original entry) with date, accounts, amounts, and a brief explanation | A+B | MVP |
| FR-005 | Support the six named books of prime entry as distinct journals | B | MVP |
| FR-006 | Post journal entries to ledger accounts and maintain running balances/footings | A+B | MVP |
| FR-007 | Link every entry to a source document type and serial reference | B | MVP |
| FR-008 | Store/attach the source document itself as evidence | A (P10) | MVP |
| FR-009 | Produce a trial balance at any date; prove Σ Dr = Σ Cr | A+B | MVP |
| FR-010 | Support subsidiary ledgers (debtors, creditors) reconciling to GL control accounts | B | MVP |
| FR-011 | Maintain a multi-column cash book and a petty cash book with analysis columns, incl. discounts allowed/received | B | Phase 2 |
| FR-012 | Support the four adjustment types at period end | A | MVP |
| FR-013 | Enforce: adjusting entries touch ≥1 B/S and ≥1 P&L account, and never Cash | A | MVP |
| FR-014 | Support contra accounts and present carrying value | A | Phase 2 |
| FR-015 | Compute depreciation on depreciable non-current assets by configurable method | A | Phase 5 |
| FR-016 | Produce Income Statement for a period | A+B | MVP |
| FR-017 | Produce Balance Sheet at a date, horizontal or vertical format | A+B | MVP |
| FR-018 | Produce Statement of Cash Flows, indirect method (direct optional) | A | Phase 3 |
| FR-019 | Produce Statement of Changes in Equity / Retained Earnings | A+B | Phase 2 |
| FR-020 | Close the period: roll P&L to Retained Earnings, carry forward B/S balances | A | MVP |
| FR-021 | Carry closing inventory forward as next period's opening inventory | A | Phase 6 |
| FR-022 | Compute cost of sales incl. deduction of internally used items | A | Phase 6 |
| FR-023 | Produce departmental P&L (B/S at overall entity level only) | A | Phase 4 |
| FR-024 | Produce the 15-ratio financial KPI pack | A | Phase 3 |
| FR-025 | Classify cash movements as Operating / Investing / Financing | A | Phase 3 |
| FR-026 | Segregate capital contributions and withdrawals (drawings/dividends) from revenue and expense | A | MVP |
| FR-027 | Record customer advance deposits as a liability on receipt, independent of delivery | B (ABX #1) | MVP |
| FR-028 | Detect and report gaps in document sequences | B (ABX #2) | MVP |
| FR-029 | Enforce a period-close checklist before a period may be locked | B (ABX #3) | MVP |
| FR-030 | Correct errors by reversing/correcting entry, never by deletion or overwrite | B | MVP |
| FR-031 | Multi-entity: separate books per legal entity, consolidated group reporting | Derived (A-02) | Phase 8 |
| FR-032 | Multicurrency: transaction / functional / presentation currency, revaluation, translation | Derived (A-03) | Phase 8 |
| FR-033 | Role-based access control with segregation of duties | Derived (A-06) | MVP |
| FR-034 | Immutable, complete audit trail of all financial data changes | Derived (A-06) | MVP |
| FR-035 | Approval workflow with configurable thresholds | Derived (A-07) | Phase 2 |
| FR-036 | Cost centre dimension on postings, with reporting and allocation | Derived (A-08) → see [05](05-cost-centres.md) | Phase 4 |
| FR-037 | Budgets by account × cost centre × period, with variance reporting | Derived (A-08) | Phase 4 |
| FR-038 | Bilingual Arabic/English UI and statement rendering, RTL-aware | Derived (A-05) | Phase 1 |
| FR-039 | Tax determination, withholding, and statutory return support | Derived (A-04) | Phase 7 |
| FR-040 | Payroll accrual and posting to GL | Derived (A-09) | Phase 7 |

---

## 1.5 Gaps — what a production system needs that the sources are silent on

These are not deficiencies in the documents (they are teaching material and were
never meant to cover this); they are simply open surface that the design must
cover on its own authority.

| ID | Gap | Why it matters | Where addressed |
|---|---|---|---|
| G-01 | No multi-entity / group structure | Al-Idan appears to operate several entities; books, close, and consolidation differ fundamentally | [02 §2.3](02-architecture.md), [04 §4.6](04-transactions-workflows.md) |
| G-02 | Single implied currency | Iraq-based operations commonly transact IQD and USD; FX gain/loss and translation are non-trivial and cannot be retrofitted cheaply | [04 §4.7](04-transactions-workflows.md) |
| G-03 | No tax treatment whatsoever | Statutory filing is mandatory and penalty-bearing | [03 §3.10](03-data-model.md), [07 §7.1](07-reporting-controls.md) |
| G-04 | No security, users, roles, or SoD | The ABX case is *entirely* a control failure; controls are the product | [02 §2.6](02-architecture.md), [07 §7.4](07-reporting-controls.md) |
| G-05 | No approval or authorisation workflow | Maker-checker is the primary defence against ABX #2 and #3 | [04 §4.4](04-transactions-workflows.md) |
| G-06 | No integration surface | Banks, POS, payroll, procurement, e-invoicing all feed the ledger | [06](06-integrations-api.md) |
| G-07 | No cost centre / dimensional concept — only the hint "P&L is prepared by each department" | This is the explicit subject of the feasibility brief | [05](05-cost-centres.md) |
| G-08 | No budgeting | Cost centre reporting without budget variance is half a feature | [05 §5.8](05-cost-centres.md) |
| G-09 | No data volumes, retention, or performance targets | Drives partitioning, balance materialisation, archive policy | [02 §2.5](02-architecture.md), assumption A-10 |
| G-10 | No migration source described | Cutover strategy depends entirely on what exists today | [08 §8.3](08-implementation-plan.md), assumption A-11 |
| G-11 | No inventory costing method named (FIFO/weighted average) — only the periodic formula | Changes the inventory subledger design substantially | Assumption A-12 |
| G-12 | Nothing on document retention, e-archiving, or legal admissibility | Iraqi law imposes retention obligations on books and vouchers | Assumption A-13 |
| G-13 | No fixed-asset register detail (componentisation, revaluation, disposal gain/loss) beyond "depreciation exists" | Phase 5 scope | [03 §3.8](03-data-model.md) |
| G-14 | Nothing on intercompany transactions or eliminations | Needed the moment there is >1 entity | [04 §4.6](04-transactions-workflows.md) |

---

## 1.6 Assumption register

**Every row here is unverified.** Items marked ⚠ change the design materially if
wrong and should be confirmed before the corresponding phase starts.

| ID | Assumption | Impact if wrong | Confirm by |
|---|---|---|---|
| A-01 | The system is being built new; there is no incumbent accounting application whose data model must be preserved | ⚠ Total — migration strategy and possibly the whole approach | Ask the finance lead what is used today (spreadsheets? Bisan? Odoo? Al-Ameen? QuickBooks?) |
| A-02 | Al-Idan is a **group of several legal entities** needing separate books plus consolidated reporting | ⚠ Multi-entity is very expensive to retrofit; cheap to design in from day 1 | Company registration documents / org chart |
| A-03 | Functional currency is **IQD**, with material USD transactions; presentation currency IQD, possibly USD for management | ⚠ Drives FX design, rounding, and statement presentation | Finance lead + last filed financial statements |
| A-04 | Iraqi statutory regime applies: corporate income tax, withholding on certain payments, social security contributions; no broad-based VAT at time of writing but sales tax applies to specific categories | High — tax module scope and statutory reports | Al-Idan's tax adviser. **Do not take my word for current Iraqi tax law — verify with a local practitioner** |
| A-05 | Users are bilingual Arabic/English; statutory output may need Arabic | Medium — UI and PDF rendering effort (RTL, Arabic numerals, fonts) | Finance team |
| A-06 | Finance team is small (roughly 3–10 people), so segregation of duties must be achievable with few staff (compensating controls, not rigid role separation) | Medium — approval design | Headcount |
| A-07 | Approval thresholds exist informally today and can be formalised | Low | Finance lead |
| A-08 | Cost centres are wanted for **management reporting and accountability by department/branch/project**, not for statutory segment reporting under IFRS 8 | ⚠ Statutory segment reporting is a stricter, different requirement | The person who requested the cost centre feature |
| A-09 | Payroll is currently run outside this system and would be integrated before being replaced | Medium — Phase 7 sequencing | HR |
| A-10 | Volume ≤ ~500k journal lines/year initially, growing; ≤ 50 concurrent users | Medium — partitioning may be deferred, not removed | Transaction counts from existing books |
| A-11 | Migration source is opening balances + open AR/AP items, not decades of transaction history | High — history migration can double effort | Finance lead |
| A-12 | Inventory, if in scope, uses **weighted average** cost | Medium — FIFO layers are a different subledger design | Finance lead / current practice |
| A-13 | Statutory retention is 7+ years for books and vouchers; electronic evidence acceptable if originals retained | Medium — archive and storage design | Legal/tax adviser |
| A-14 | The stack is fixed: **Laravel 13 + Filament 5 + PostgreSQL 16** (already scaffolded in this repository) | Low — this is observed fact, not assumption, but noted for completeness | `composer.json` in this repo |
| A-15 | Fiscal year = calendar year (Jan–Dec), 12 monthly periods + an adjustment period 13 | Low — configurable either way | Finance lead |

---

## 1.7 Recommended next action on this document

Before Phase 1 design is frozen, hold a **2-hour requirements workshop** with the
finance lead to close A-01, A-02, A-03, A-08, A-11 and A-15. Those six answers
determine roughly 70% of the remaining design risk. Everything else in this
document set can proceed in parallel without them.
