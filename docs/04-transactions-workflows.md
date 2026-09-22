# 04 — Transactions & Workflows

**Reads from:** [01](01-source-analysis.md), [03](03-data-model.md)

---

## 4.1 Journal entry lifecycle

```
                  ┌──────────────────────── amend ◀───────────┐
                  ▼                                            │
  ┌────────┐   submit   ┌───────────────────┐  reject   ┌──────┴──────┐
  │ DRAFT  ├───────────▶│ PENDING_APPROVAL  ├──────────▶│  REJECTED   │
  └───┬────┘            └─────────┬─────────┘            └─────────────┘
      │                  approve  │
      │ (below threshold)         ▼
      │                    ┌─────────────┐
      └───────────────────▶│  APPROVED   │
                           └──────┬──────┘
                              post │  (atomic: validate → number → write lines
                                   │   → update gl_balances → hash → audit)
                                   ▼
                           ┌─────────────┐   reverse    ┌─────────────┐
                           │   POSTED    ├─────────────▶│  REVERSED   │
                           │ (immutable) │              │ (+ new entry)│
                           └─────────────┘              └─────────────┘
```

Three properties of this lifecycle are non-negotiable:

- **Draft entries never touch balances.** A draft is a proposal; only `posted`
  affects the ledger, `gl_balances`, or any report.
- **Posted entries are immutable** (FR-030, [02 §2.6](02-architecture.md)). There
  is no edit and no delete. The only correction is a reversal plus a fresh entry,
  both of which remain visible — which is precisely what an auditor needs and what
  ABX Motors did not have.
- **Numbering happens at post, not at draft.** Allocating `entry_no` on draft
  creation produces gaps whenever a draft is abandoned, and gaps are exactly what
  FR-028 must be able to treat as a red flag. The sequence is drawn inside the
  posting transaction from a per-`(entity, journal, fiscal_year)` counter row
  locked with `SELECT … FOR UPDATE`.

### Reversal semantics

Two reversal styles, chosen by the user and recorded:

| Style | Entry created | Use when |
|---|---|---|
| **Reversal** (default) | Mirror entry with Dr/Cr swapped, dated in an open period | The original was wrong; both entries stay visible |
| **Storno / negative** | Same sides, negative amounts | Local practice requires gross-up suppression; off by default because it breaks the "no zero/negative line" constraint and confuses turnover analysis |

Reversal requires a `reversal_reason` from a controlled list (data entry error,
wrong period, wrong account, wrong amount, duplicate, cancelled transaction,
audit adjustment) — free text alone produces reasons like "fix" that tell a
reviewer nothing.

### Auto-reversing accruals

Type 2 and Type 4 adjustments (Doc A) are routinely reversed on the first day of
the next period. `journal_entries.auto_reverse_on` drives a scheduled job that
creates and posts the mirror entry when the next period opens. This prevents the
classic double-count: accrue an expense in December, pay it in January against the
expense account instead of the accrual, and overstate the January cost.

---

## 4.2 Posting engine — the validation set

Every rule below runs inside the posting transaction. Failing any one aborts the
whole post; there is no partial state.

| # | Rule | Source |
|---|---|---|
| V-01 | Σ debit = Σ credit, in transaction currency **and** in functional currency | FR-002, P12 |
| V-02 | ≥2 lines; no line with both or neither of debit/credit | FR-002 |
| V-03 | Fiscal period exists, belongs to the entity, and status = `open` (or `soft_closed` with GL override permission) | FR-029 |
| V-04 | `entry_date` falls inside the fiscal period | P5 |
| V-05 | Every account is active, postable (leaf), and enabled for the entity | FR-001 |
| V-06 | No manual posting to a control account — subledger only | FR-010 |
| V-07 | `cost_centre_id` present wherever `accounts.requires_cost_centre` and the enforcement date has passed | FR-036, [05 §5.5](05-cost-centres.md) |
| V-08 | Cost centre is postable, active, and within its effective dates at `entry_date` | FR-036 |
| V-09 | All lines share one `entity_id` — no cross-entity entry | [02 §2.3](02-architecture.md) |
| V-10 | Exchange rate present and > 0 when transaction currency ≠ functional currency | FR-032 |
| V-11 | Functional amounts equal transaction amounts × rate, within a rounding tolerance; any residue posts to the configured rounding account | FR-032 |
| V-12 | If `is_adjusting`: ≥1 BS account, ≥1 P&L account, **no cash/bank account** | FR-013 (Doc A) |
| V-13 | Manual entries carry `source_document_no` and ≥1 attachment | FR-007/008, P10 |
| V-14 | Approval satisfied where the threshold matrix requires it, and approver ≠ creator | FR-035 |
| V-15 | Journal permits this entry type (`journals.allows_manual_entry`) | FR-005 |
| V-16 | Idempotency key unused — a retried request cannot post twice | — |
| V-17 | Tax lines consistent with their base lines where a tax code is present | FR-039 |
| V-18 | Entry not already posted (status transition legal) | — |

Implementation shape:

```php
DB::transaction(function () use ($draft) {
    $this->validator->assertValid($draft);                 // V-01..V-18
    $entry = $this->writer->createHeader($draft);
    $entry->entry_no = $this->sequencer->next($draft);      // FOR UPDATE, gapless
    $this->writer->createLines($entry, $draft->lines);
    $this->balances->apply($entry);                        // UPSERT gl_balances
    $this->hasher->chain($entry);                          // tamper-evidence
    $entry->markPosted(auth()->id());
});                                                        // audit trigger fires
```

---

## 4.3 Transaction flows

Journal codes below are Doc B's six books of prime entry (FR-005), seeded in
`journals`: **SDB** Sales Day Book · **PDB** Purchases Day Book · **RIB** Returns
Inwards · **ROB** Returns Outwards · **CB** Cash Book · **PCB** Petty Cash Book ·
**GJ** General Journal.

### Credit sale → settlement

| Step | Document | Journal | Entry |
|---|---|---|---|
| 1 | Sales invoice issued | SDB | Dr 1200 Trade Receivables · Cr 4000 Sales · Cr 2300 Sales Tax Payable |
| 2 | Goods delivered (perpetual inventory) | GJ | Dr 5000 Cost of Sales · Cr 1300 Inventory |
| 3 | Customer returns goods | RIB | Dr 4500 Returns Inwards · Dr 2300 Tax · Cr 1200 Trade Receivables |
| 4 | Receipt banked | CB | Dr 1100 Bank · Cr 1200 Trade Receivables |
| 5 | Settlement discount given | CB | Dr 7450 Discounts Allowed · Cr 1200 Trade Receivables |
| 6 | Debt written off | GJ | Dr 7800 Irrecoverable Debts · Cr 1200 Trade Receivables |

### Advance deposit — the ABX #1 control (FR-027)

This is the flow the auditor in Doc B found missing entirely, so it is worth
setting out explicitly:

| Step | Journal | Entry |
|---|---|---|
| 1. Customer pays a reservation deposit; **receipt issued** | CB | Dr 1000 Cash · **Cr 2350 Customer Deposits (liability)** |
| 2. Goods delivered, invoice raised | SDB | Dr 1200 Trade Receivables · Cr 4000 Sales · Cr 2300 Tax |
| 3. Deposit applied against the invoice | GJ | Dr 2350 Customer Deposits · Cr 1200 Trade Receivables |
| 4. Balance settled | CB | Dr 1100 Bank · Cr 1200 Trade Receivables |

The system makes step 1 unavoidable: **issuing a receipt is itself a posting
event.** There is no path that produces a receipt document without a journal
entry, because the receipt record and its `journal_entry_id` are written in the
same transaction. Unapplied deposits then appear on a standing ageing report that
the period-close checklist requires the controller to clear or explain.

### Credit purchase → payment

| Step | Document | Journal | Entry |
|---|---|---|---|
| 1 | Purchase order raised | — | No entry (commitment only; optionally encumbrance in a statistical account) |
| 2 | Goods received | GJ | Dr 1300 Inventory · Cr 2150 GRNI (goods received not invoiced) |
| 3 | Supplier invoice matched | PDB | Dr 2150 GRNI · Dr 2310 Recoverable Tax · Cr 2000 Trade Payables |
| 4 | Return to supplier | ROB | Dr 2000 Trade Payables · Cr 5300 Returns Outwards |
| 5 | Payment by cheque | CB | Dr 2000 Trade Payables · Cr 1100 Bank |
| 6 | Discount received | CB | Dr 2000 Trade Payables · Cr 4600 Discounts Received |
| 7 | Withholding tax retained | CB | Dr 2000 Trade Payables · Cr 1100 Bank · Cr 2320 WHT Payable |

The GRNI account (2150) is what makes ABX #2 detectable: goods received with no
invoice leave a growing credit balance in a clearing account that the close
checklist forces someone to explain.

### Petty cash (imprest)

Float established Dr 1010 Petty Cash / Cr 1100 Bank. Payment vouchers accumulate
without posting; reimbursement posts the whole batch through PCB, Dr expense
accounts by cost centre / Cr 1100 Bank, restoring the float. The imprest amount is
a reconciliation target: vouchers on hand + cash on hand must equal the float at
all times.

### Fixed asset lifecycle

Acquisition Dr 1400 Asset / Cr 2000 or 1100 → monthly depreciation Dr 7600
Depreciation Expense *(by cost centre)* / Cr 1800 Accumulated Depreciation → on
disposal, remove cost and accumulated depreciation, recognise proceeds, and book
the balancing figure to 8700 gain or 8750 loss.

### Payroll

Dr 6000 Wages, Dr 6100 Employer Social Security *(both by cost centre)* / Cr 2400
Net Pay Payable, Cr 2410 Employee Social Security Payable, Cr 2420 Employee Income
Tax Withheld. Payment of net pay and remittance of the statutory deductions are
separate later entries through CB.

---

## 4.4 Approval workflow (FR-035)

```sql
CREATE TABLE approval_rules (
    id bigserial PRIMARY KEY,
    entity_id bigint REFERENCES entities(id),
    document_type varchar(40) NOT NULL,   -- journal_entry|vendor_bill|vendor_payment|…
    condition jsonb NOT NULL,             -- {"amount_gte":5000000,"journal":"GJ"}
    step_no smallint NOT NULL,
    approver_role varchar(50),
    approver_user_id bigint REFERENCES users(id),
    is_mandatory boolean NOT NULL DEFAULT true,
    UNIQUE (entity_id, document_type, step_no, condition)
);
CREATE TABLE approval_requests (
    id bigserial PRIMARY KEY,
    document_type varchar(40) NOT NULL, document_id bigint NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'pending',
    current_step smallint NOT NULL DEFAULT 1,
    requested_by bigint NOT NULL REFERENCES users(id),
    requested_at timestamptz NOT NULL DEFAULT now()
);
CREATE TABLE approval_actions (
    id bigserial PRIMARY KEY,
    approval_request_id bigint NOT NULL REFERENCES approval_requests(id),
    step_no smallint NOT NULL, actor_user_id bigint NOT NULL REFERENCES users(id),
    action varchar(20) NOT NULL,          -- approve | reject | delegate | recall
    comment text, acted_at timestamptz NOT NULL DEFAULT now()
);
```

Suggested starting matrix (amounts in IQD; tune with the finance lead):

| Document | Condition | Approvals |
|---|---|---|
| Manual GJ | any | Financial controller |
| Manual GJ | > 50,000,000 or `is_adjusting` | Controller + CFO |
| Sales invoice | standard | AR clerk posts; no approval |
| Sales invoice | credit limit exceeded | Controller |
| Credit note | any | Controller (the classic fraud vector) |
| Vendor bill | matched 3-way within tolerance | Auto-approve |
| Vendor bill | unmatched / over tolerance | Department head + Controller |
| Vendor payment | any | Controller + bank signatory |
| Vendor/customer master change (bank details) | any | Controller — **and notify the previous bank detail holder** |
| Period close | any | Controller |
| Cost centre create/close | any | Controller |

Two rules apply to every row: **the approver may never be the creator** (enforced
by a DB check, [03 §3.4](03-data-model.md)), and **delegation is explicit and
time-boxed**, never a shared login.

---

## 4.5 Period close (FR-020, FR-029)

Doc A's periodicity principle plus ABX #3 make this the most control-heavy process
in the system. Three states, deliberately:

`open` → `soft_closed` (subledgers locked, GL open for adjustments) → `closed`
(nothing posts without a controller override) → `permanently_closed` (nothing
posts at all, ever; set after the statutory filing).

### The close checklist — each item a hard gate

| # | Gate | Auto? |
|---|---|---|
| 1 | All subledger documents posted; no drafts in the period | Auto |
| 2 | Document sequence gap report clean or explained | Auto + sign-off |
| 3 | Bank reconciliation complete for every bank account | Manual sign-off |
| 4 | AR subledger total = 1200 control account balance | Auto |
| 5 | AP subledger total = 2000 control account balance | Auto |
| 6 | Inventory subledger = 1300 control balance | Auto |
| 7 | Fixed-asset register = 1400s cost and 1800s accumulated depreciation | Auto |
| 8 | Depreciation run posted | Auto |
| 9 | Accruals and prepayments posted (Doc A types 1–4) | Manual |
| 10 | Unapplied customer deposits reviewed (ABX #1) | Manual sign-off |
| 11 | GRNI (2150) aged and explained (ABX #2) | Auto + sign-off |
| 12 | **All suspense and clearing accounts (9500–9899) = 0** | Auto |
| 13 | FX revaluation of monetary balances posted | Auto |
| 14 | Intercompany balances agreed with counterparty entity | Auto |
| 15 | Tax provision calculated and posted | Manual |
| 16 | Cost centre allocations run ([05 §5.6](05-cost-centres.md)) | Auto |
| 17 | Trial balance balances; all P&L lines carry a cost centre where required | Auto |
| 18 | Management pack reviewed and signed by the controller | Manual |

The period cannot move to `closed` while any auto gate fails. Manual gates require
a named sign-off stored with timestamp and user — this is the artefact an auditor
asks for and the reason ABX Motors' finance department had no answer.

### Year-end (FR-020)

1. Close period 12; open period 13 (adjustments) for audit entries.
2. Post audit adjustments in period 13 — flagged so that management and statutory
   figures can be compared.
3. **Closing entry:** every P&L account (4000–9499) closed to `3900 Current Year
   Earnings`; the resulting balance transferred to `3200 Retained Earnings`
   — this is Doc A's *"the account called Retained Earnings"* link between the P&L
   and the balance sheet, made concrete.
4. Roll balance-sheet closing balances to next-year opening balances (an `opening`
   journal entry, so opening balances are themselves auditable rather than
   materialising from nowhere).
5. Closing inventory becomes next year's opening inventory (FR-021).
6. Lock the fiscal year; set `permanently_closed` after filing.

---

## 4.6 Intercompany (G-14)

**Never one entry spanning two entities** (V-09). An intercompany transaction is
two independently balanced entries sharing an `intercompany_link_id` (uuid):

Entity A pays a shared expense of 1,000,000 on behalf of Entity B:

| Entity | Entry |
|---|---|
| A | Dr 2900.B Due from Entity B 1,000,000 · Cr 1100 Bank 1,000,000 |
| B | Dr 6500 Rent Expense *(cost centre)* 1,000,000 · Cr 2950.A Due to Entity A 1,000,000 |

Mechanics:
- Reciprocal due-to/due-from accounts, one pair per counterparty entity.
- The counterparty entry is generated automatically and routed for approval in the
  receiving entity — proposed, not force-posted, so B's controller retains control
  of B's books.
- An **intercompany matching report** lists unmatched pairs; a close gate (#14).
- On consolidation, matched pairs eliminate automatically; unmatched pairs are
  reported as a consolidation exception rather than silently plugged.

### Consolidation

Elimination entries post to a dedicated **consolidation-only entity**, so the
legal entities' books stay statutory-clean:
1. Translate each entity from functional to presentation currency (§4.7).
2. Eliminate intercompany balances and transactions.
3. Eliminate unrealised profit in intercompany inventory.
4. Eliminate investment against subsidiary equity; recognise non-controlling interest.

---

## 4.7 Multicurrency (FR-032)

Three currency layers, and conflating them is the usual source of unexplainable
FX movements:

| Layer | Meaning | Stored in |
|---|---|---|
| **Transaction** | What the document is denominated in | `journal_lines.currency_code`, `debit_amount` |
| **Functional** | The entity's own reporting currency (A-03: IQD) | `functional_debit/credit` |
| **Presentation** | Group consolidation currency | Computed at consolidation |

Rules:
- Both transaction and functional amounts are stored **on every line at posting
  time**. Never recompute history from a rate table — rates get corrected, and
  history must not move.
- Rate types are distinct: `spot` for transactions, `average` for P&L translation,
  `closing` for balance-sheet translation, `budget` for budget comparison.
- **Realised FX** (8400/8450) arises on settlement: the difference between the
  functional value at invoice date and at payment date.
- **Unrealised FX** arises at period end on monetary balances only (cash,
  receivables, payables, loans). Non-monetary items (inventory, fixed assets,
  prepayments) stay at historic rate — Doc A's historical-cost principle P4.
- Translation differences on consolidating a foreign entity go to the **FX
  translation reserve (3600s, CTA)** in equity, not to P&L.
- **IQD has no minor unit in practice.** Set `currencies.decimal_places = 0` for
  IQD and round at the line, accumulating any residue to a rounding account.
  Storing IQD with two decimals produces amounts no bank statement will ever match.

Worked example — USD invoice in an IQD-functional entity:

| Date | Event | Rate | Entry (functional IQD) |
|---|---|---|---|
| 01 Mar | Invoice USD 10,000 | 1,310 | Dr 1200 AR 13,100,000 · Cr 4000 Sales 13,100,000 |
| 31 Mar | Revaluation | 1,320 | Dr 1200 AR 100,000 · Cr 8400 Unrealised FX Gain 100,000 |
| 15 Apr | Receipt USD 10,000 | 1,305 | Dr 1100 Bank 13,050,000 · Dr 8450 Realised FX Loss 150,000 · Cr 1200 AR 13,200,000 |

The AR line clears exactly, and the net P&L effect is a 50,000 loss — the true
economic outcome. Getting this wrong (revaluing at settlement only, or revaluing
non-monetary items) is the most common multicurrency defect I would expect a
review to find.
