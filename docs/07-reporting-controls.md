# 07 — Reporting, Controls & Auditability

**Reads from:** [01](01-source-analysis.md), [03](03-data-model.md), [04](04-transactions-workflows.md), [05](05-cost-centres.md)

---

## 7.1 Financial reports

### Statutory / primary statements

| Report | Source requirement | Notes |
|---|---|---|
| **Trial Balance** | FR-009, Doc A+B | By account; optional cost centre column; opening / movement / closing; must prove Σ Dr = Σ Cr |
| **Income Statement (P&L)** | FR-016 | Period + YTD, comparative prior year, optional by cost centre ([05 §5.8](05-cost-centres.md)) |
| **Balance Sheet** | FR-017 | At a date, **horizontal or vertical format** (Doc A specifies both), comparative |
| **Statement of Cash Flows** | FR-018 | Indirect default, direct optional — see mapping below |
| **Statement of Changes in Equity** | FR-019 | Opening equity, capital introduced, profit, drawings/dividends, closing |
| **Notes / disclosures** | P6 | Attachment-backed, versioned with the statement pack |

### Cash flow mapping (Doc A's table, implemented)

Doc A supplies a complete movement→adjustment table for the indirect method. It is
encoded directly as report configuration rather than hard-coded logic, so it can
be inspected and adjusted:

| Balance movement | Adjustment to Net Income | Activity |
|---|---|---|
| Current asset increase | Deduct | Operating |
| Current asset decrease | Add | Operating |
| Current liability increase | Add | Operating |
| Current liability decrease | Deduct | Operating |
| Depreciation (non-cash) | Add | Operating |
| Amortisation (non-cash) | Add | Operating |
| Gain on long-term asset disposal | Deduct | Operating (reclass to Investing) |
| Loss on long-term asset disposal | Add | Operating (reclass to Investing) |
| Fixed & other assets increase | Deduct | Investing |
| Fixed & other assets decrease | Add | Investing |
| Long-term liability increase | Add | Financing |
| Long-term liability decrease | Deduct | Financing |
| Capital increase | Add | Financing |
| Capital decrease | Deduct | Financing |
| Dividends paid | Deduct | Financing |

Implementation: `accounts.cash_flow_class` classifies each account; the report
walks period-over-period movements in `gl_balances` and applies the table. A
built-in proof check asserts that computed closing cash equals the actual closing
balance of the cash and bank accounts — if it does not, the report says so rather
than silently presenting a plausible-looking wrong number.

### Operational reports

| Report | Purpose |
|---|---|
| General Ledger detail | Every line for an account/period, drill-through to source document |
| Journal listing / day books | Per book of prime entry (FR-005) — the Sales Day Book *is* a report |
| **Statement of account** | Per customer — Doc B source document type #10 |
| AR ageing | Current / 30 / 60 / 90 / 120+, by customer, with **unapplied receipts and deposits shown separately** (ABX #1) |
| AP ageing | Same, plus due-date forecast |
| Cash book / petty cash book | Multi-column with analysis (FR-011) |
| Bank reconciliation statement | Per account, per period |
| Fixed asset register | Cost, accumulated depreciation, NBV, by category and cost centre |
| Depreciation schedule | Forward projection |
| Inventory valuation | Quantity, value, method; movement report by reason incl. internal use (FR-022) |
| Tax reports | Output/input tax, withholding certificates, return working papers |
| Document sequence gap report | FR-028 (ABX #2) |
| Unposted / draft report | Everything not yet in the ledger |
| Audit trail report | By user, by record, by date range |

### Management reports

Cost centre P&L, matrix P&L, budget variance, contribution, allocation statement,
trend, manager dashboard, unassigned — all detailed in [05 §5.8](05-cost-centres.md).

---

## 7.2 Reports answering the ABX Motors findings

Doc B's audit case is effectively a specification for three standing reports:

| Finding | Report | Gate |
|---|---|---|
| Unrecorded customer deposits | **Unapplied receipts & deposits ageing** — every receipt not fully applied, aged, with the liability balance proved to account 2350 | Close checklist #10 |
| Missing purchase invoices | **Document sequence gap report** + **GRNI ageing** (goods received, no invoice) | Close checklist #2, #11 |
| Year-end errors and omissions | **Close readiness dashboard** — all 18 gates with pass/fail and sign-off status | Close checklist #1–18 |

---

## 7.3 Financial KPI pack (FR-024)

Doc A specifies exactly 15 ratios. All are built in, computed from `gl_balances`
plus the cash-flow report, with drill-through to the components:

**Liquidity**
1. Current ratio = Current Assets / Current Liabilities
2. Quick ratio = (Cash + Marketable Securities + Receivables) / Current Liabilities
3. Receivable turnover = Net Sales / Average Accounts Receivable
4. Average days' sales uncollected = Days in Year / Receivable Turnover
5. Inventory turnover = Cost of Goods Sold / Average Inventory
6. Average days' inventory on hand = Days in Year / Inventory Turnover
7. Payable turnover = (COGS ± Change in Inventory) / Average Accounts Payable
8. Average days' payable = Days in Year / Payable Turnover

**Profitability**
9. Profit margin = Net Income / Net Sales
10. Asset turnover = Net Sales / Average Total Assets
11. Return on assets = Net Income / Average Total Assets
12. Return on equity = Net Income / Average Stockholders' Equity

**Long-term solvency**
13. Debt to equity = Total Liabilities / Stockholders' Equity
14. Interest coverage = (Income Before Tax + Interest Expense) / Interest Expense

**Cash-flow adequacy**
15. Cash flow yield = Net Cash Flows from Operating Activities / Net Income
    · Cash flows to sales · Cash flows to assets
    · Free cash flow = NCF from Operations − Dividends − Net Capital Expenditures

Each ratio's definition is stored as configuration (account ranges + formula), so
the finance team can see exactly which accounts feed a number. Doc A's own caution
is worth reproducing in the UI: *"The primary purpose of ratios is to point out
areas needing further investigation."*

---

## 7.4 Internal controls

Controls are grouped by the failure they prevent.

### Preventive

| Control | Mechanism |
|---|---|
| Unauthorised posting | RBAC + approval matrix ([04 §4.4](04-transactions-workflows.md)) |
| Segregation of duties | Creator ≠ approver, enforced by DB check; SoD override logged and reported |
| Posting to a closed period | Period status check V-03; override requires controller permission and is logged |
| Unbalanced entry | DB CHECK constraint — physically impossible |
| Posting to a control account manually | `accounts.is_control_account` + CHECK, V-06 |
| Missing dimension | `requires_cost_centre` + V-07 |
| Duplicate supplier invoice | `UNIQUE (vendor_id, vendor_invoice_no)` |
| Unsupported entry | Attachment required to post manual entries (P10, FR-008) |
| Payment to a changed bank account | Vendor bank-detail change is an approved document, with notification |
| Credit note abuse | Every credit note requires approval |
| Over-limit sales | Credit limit check at invoice creation |

### Detective

| Control | Mechanism |
|---|---|
| Missing documents | Sequence gap report (FR-028) |
| Unrecorded liabilities | GRNI ageing; unapplied receipt ageing |
| Subledger drift | Automated subledger↔control reconciliation, daily not just at close |
| Balance corruption | Nightly `gl_balances` vs `journal_lines` recomputation with alerting |
| Tampering | Hash chain over posted entries ([02 §2.6](02-architecture.md)) |
| Unusual activity | Exception reports: round-number entries, entries posted outside business hours, entries by dormant users, entries just under an approval threshold, back-dated entries |
| Access creep | Quarterly access review checklist |

That "just under an approval threshold" report is worth singling out: a cluster of
entries at 49,900,000 against a 50,000,000 threshold is the single most reliable
fraud indicator in a payables system.

### Corrective

Reversal-only correction with mandatory reason codes (FR-030); close-checklist
gates that must be cleared, not waived; an exception log the controller reviews
monthly.

---

## 7.5 Reconciliation processes

| Reconciliation | Frequency | Tolerance | Owner |
|---|---|---|---|
| Bank account ↔ statement | Weekly, mandatory at close | 0 | Cashier prepares, controller reviews |
| AR subledger ↔ control 1200 | Daily automated, close gate | 0 | AR clerk |
| AP subledger ↔ control 2000 | Daily automated, close gate | 0 | AP clerk |
| Inventory subledger ↔ control 1300 | Monthly + physical count | Policy % | Inventory + controller |
| Fixed asset register ↔ 1400s/1800s | Monthly | 0 | GL accountant |
| Petty cash: vouchers + cash = float | Weekly, surprise count | 0 | Independent counter |
| Intercompany balances | Monthly, close gate | 0 | GL accountant both sides |
| Suspense/clearing 9500–9899 = 0 | Close gate | 0 | GL accountant |
| Payroll control accounts | Monthly | 0 | Payroll + GL |
| Tax accounts ↔ returns filed | Per return period | 0 | Tax accountant |
| `gl_balances` ↔ `journal_lines` | Nightly automated | 0 | System (alert) |

Reconciliations are **documents in the system**, not spreadsheets: preparer,
reviewer, date, reconciling items, attachments, sign-off. A reconciliation that
lives on someone's desktop is not a control, because nobody can prove it happened.

### Bank reconciliation model

```sql
CREATE TABLE bank_reconciliations (
    id bigserial PRIMARY KEY,
    bank_account_id bigint NOT NULL REFERENCES bank_accounts(id),
    fiscal_period_id bigint NOT NULL REFERENCES fiscal_periods(id),
    statement_date date NOT NULL,
    statement_closing_balance numeric(20,4) NOT NULL,
    ledger_closing_balance numeric(20,4) NOT NULL,
    unpresented_cheques numeric(20,4) NOT NULL DEFAULT 0,
    deposits_in_transit numeric(20,4) NOT NULL DEFAULT 0,
    other_adjustments numeric(20,4) NOT NULL DEFAULT 0,
    difference numeric(20,4) GENERATED ALWAYS AS (
        statement_closing_balance - ledger_closing_balance
        + unpresented_cheques - deposits_in_transit - other_adjustments) STORED,
    status varchar(20) NOT NULL DEFAULT 'draft',  -- draft | prepared | reviewed | approved
    prepared_by bigint REFERENCES users(id), reviewed_by bigint REFERENCES users(id),
    approved_at timestamptz,
    UNIQUE (bank_account_id, fiscal_period_id),
    CHECK (status <> 'approved' OR difference = 0)   -- cannot approve an unbalanced rec
);
```

The final CHECK is deliberate: an approved reconciliation with a residual
difference is not a reconciliation.

---

## 7.6 Auditability

What an external auditor will ask for, and where it comes from:

| Auditor request | System answer |
|---|---|
| Complete GL for the year, exportable | GL detail export, CSV/XLSX, with a row count and control total |
| Proof no entries were deleted | Append-only ledger + gapless numbering + hash chain |
| Who posted this entry, when, and who approved it | On the entry itself |
| Source document for this entry | Attachment, immutable object key + SHA-256 |
| All entries above X, or by user Y, or outside business hours | Exception reports (§7.4) |
| All manual journal entries in the year | Filter `journal = GJ`, `source_type = manual` |
| All post-close adjustments | Period 13 flag + `is_adjusting` |
| Changes to the chart of accounts | `audit_log` on `accounts` |
| User access at a point in time | `audit_log` on `role_user`/`permissions` |
| Segregation-of-duties evidence | Role matrix + SoD override report |
| Reconciliations for each month | Reconciliation documents with sign-offs |
| Trial balance at any historical date | `gl_balances`, reproducible |

**A read-only `auditor` role** ships from Phase 1: sees everything including the
audit trail, can export, cannot write anything. Giving an auditor a spreadsheet
export and a promise is what turns a two-week audit into a six-week one.

### Report reproducibility

A report run for a closed period must return identical output every time. This is
guaranteed by the immutability of posted entries plus period locking. Where a
prior-period adjustment is genuinely necessary, it posts to the **current** period
with a disclosed prior-period reference rather than reopening history. Every
generated statement pack is archived as a PDF with its parameters, generation
timestamp, and a hash — so "the version we gave the bank in March" is retrievable.
