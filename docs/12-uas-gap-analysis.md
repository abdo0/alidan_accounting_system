# 12 — Gap Analysis: النظام المحاسبي الموحد vs the current build

**Reads from:** [10 — Source Analysis](10-uas-source-analysis.md)
**Assessed against:** branch `build/accounting-system` as at 2026-09-17 — foundations,
bilingual UI, general ledger and posting engine complete; 63 tests green; subledgers,
reporting and UI not yet built.

**This is an alignment checklist, not a compliance certificate.** Al-Idan is private
sector and outside the standard's stated scope ([10 §10.2](10-uas-source-analysis.md)).
Nothing here should be represented to a third party as statutory compliance.

Legend: **✅ meets** · **⚠ partial** · **❌ gap** · **➕ we exceed the standard** ·
**🔁 conflict — the standard and our design disagree**

---

## 12.1 Summary

| Area | Verdict |
|---|---|
| Chart of accounts structure | ❌ Fundamentally different; replacement required |
| Posting mechanics and double entry | ✅ Already conformant, and stricter than required |
| Document control | ✅ / ➕ Meets all six rules; exceeds on evidence immutability |
| Books and journals | ⚠ Right shape, wrong names and columns |
| Financial statements | ❌ Wrong set entirely; Value Added statement absent |
| Contra / off-balance accounts | ❌ No equivalent mechanism |
| Cost accounting | 🔁 Conflicts with a documented design decision |
| Depreciation | ⚠ Mechanism correct, rates invented rather than statutory |
| Arabic localisation | ✅ / ➕ Already bilingual and RTL; exceeds what the standard asks |
| Computerisation controls (Ch 10) | ✅ / ➕ Meets all seven; exceeds on four dimensions the standard omits |
| Auditability | ➕ Substantially exceeds |

The headline: **the accounting machinery we built is sound and mostly conformant; the
accounting *content* — chart, statements, cost structure — is wrong and must be
replaced.** That is the better way round, because the machinery is the expensive part.

---

## 12.2 Data model and chart of accounts

| # | Requirement | Status | Detail |
|---|---|---|---|
| D-1 | Nine top-level classes (`1`–`4` financial, `5`–`9` cost-centre controls) | ❌ | We have five IFRS-style classes. `accounts.account_class` CHECK constraint must be altered |
| D-2 | No separate equity class — رأس المال and الاحتياطيات are liabilities | ❌ | We model `equity` as a class |
| D-3 | Hierarchical decimal codes, 1–6 digits, parent = prefix, digit `0` unused | ❌ | We use 4-digit range-based codes |
| D-4 | Named levels (إجمالي / عام / مساعد / فرعي / جزئي / تحليلي) | ❌ | No `account_level` column |
| D-5 | Minimum 3 levels, maximum 6 | ❌ | Not modelled |
| D-6 | Posting at level 3 or deeper, at the branch leaf | ⚠ | We enforce leaf-only posting (V-05) but not the depth ≥ 3 rule |
| D-7 | Deliberate gaps in sibling numbering (`17`, `27`, `233`, `328`, `371`) | ⚠ | Seeder must treat the chart as an enumeration, never inferring |
| D-8 | Activity classification: جاري/استثماري and اعتيادي/استثنائي | ❌ | No columns on `journal_lines` |
| D-9 | Value-added components derivable from the chart | ❌ | Depends on D-1 |
| D-10 | Chart file is the validation authority for permitted codes (Ch 10, ملف الدليل) | ✅ | `entity_account_settings` + V-05 already do exactly this |

**Not stated in the source, therefore our decision to record:** neither `is_postable` nor
`normal_balance` appears anywhere in Chapter 1. Both are derived — postability from
depth ≥ 3 and childlessness, normal balance from class with lexical exceptions. See
[13](13-uas-redesign.md).

---

## 12.3 Posting, books and documents

| # | Requirement | Status | Detail |
|---|---|---|---|
| P-1 | Double entry, debit = credit | ✅ | DB CHECK + deferred constraint trigger; physically unrepresentable otherwise |
| P-2 | Documents numbered and used **in sequence**, missing ones identifiable (control rule 2) | ✅ | Gapless `Sequencer` per (entity, journal, year); gap report planned |
| P-3 | Transaction date on every document | ✅ | `entry_date` NOT NULL, validated inside its period (V-04) |
| P-4 | Unit name and address on documents | ⚠ | `entities` carries both; not yet rendered on forms |
| P-5 | Clear document title showing purpose | ⚠ | Journals seeded with names; forms not built |
| P-6 | Secure, retrievable retention | ✅ / ➕ | Attachments content-addressed, SHA-256, immutable by DB rule — stronger than "locked cupboard" |
| P-7 | Multiple copies with original distinguishable | n/a | An artefact of paper; superseded by the audit trail |
| P-8 | The six prescribed source documents | ❌ | None built. AR/AP not yet started |
| P-9 | Unified journal voucher typed 3–8 by journal | ⚠ | One `journal_entries` table with `journal_id` — the right model, wrong type codes |
| P-10 | Seven journals, only اليومية العامة and الأستاذ العام mandatory | ⚠ | 13 journals seeded incl. all six subsidiaries, but names/codes are ours not the standard's |
| P-11 | Columnar analysis journals with fixed level-3 account columns + `حسابات أخرى` | ❌ | We store lines, not columns. This is a **reporting** shape, not a storage shape — produce it as a report |
| P-12 | Monthly aggregate entry (قيد شهري) to the general journal at level 2 | ❌ | Not implemented |
| P-13 | Monthly trial balances at levels 2 and 3, reconciled | ❌ | Not implemented |
| P-14 | Five approval signatures on payment and journal vouchers | ⚠ | Approval engine exists with configurable steps; five roles must be seeded |

On **P-11**: it would be a mistake to store journals column-wise. The standard describes
a paper register; the same information is a pivot over `journal_lines`. We satisfy the
requirement by rendering it, and gain the ability to produce it at any level.

---

## 12.4 Financial statements

| # | Requirement | Status |
|---|---|---|
| S-1 | الميزانية العامة, vertical, contra accounts shown below the totals | ❌ |
| S-2 | حساب الإنتاج والمتاجرة والأرباح والخسائر والتوزيع (إنموذج ١) | ❌ |
| S-3 | حساب الإيرادات والمصروفات والتوزيع (إنموذج ٢) — service cos. without cost centres | ❌ |
| S-4 | حساب الأرباح والخسائر للتعهدات والمقاولات المنجزة — contractors, per-project matrix | ❌ |
| S-5 | كشف العمليات الجارية, two stages, `384` split across them | ❌ |
| S-6 | كشف التدفق النقدي, three activity sections | ⚠ Planned as IFRS indirect method; **the UAS form differs** |
| S-7 | **كشف إجمالي القيمة المضافة** | ❌ No equivalent anywhere |
| S-8 | **كشف توزيع إجمالي القيمة المضافة**, reconciling to S-7 | ❌ |
| S-9 | 26 analytical statements | ❌ |
| S-10 | Universal column frame with prior-year comparative and كشف cross-reference | ❌ |

Our planned statement set (P&L, balance sheet, SOCE, IFRS cash flow) maps onto **none of
these one-for-one**. This is the largest single body of new work.

Worth noting for scoping: **S-2 vs S-3 depends on whether Al-Idan operates cost centres.**
If it does not, the simpler إنموذج ٢ applies and a large part of the cost-accounting work
falls away. This is a live question for [15](15-uas-inputs-required.md).

---

## 12.5 Cost accounting — conflicts

Detailed in [13 §13.5](13-uas-redesign.md). Summarised:

| # | Issue | Status |
|---|---|---|
| C-1 | Cost centres are chart classes `5`–`9`, not an orthogonal dimension | 🔁 Contradicts `docs/05` |
| C-2 | Element `٣٥` is **never** allocated to a cost centre | 🔁 Our V-07 would reject the conformant behaviour |
| C-3 | Step-down ordering by **count of centres served** | ⚠ Our ordering is a manual sequence |
| C-4 | Service centres charge marketing and administrative centres too | ⚠ |
| C-5 | Capital-operations centres capitalise into assets, not cost of sales | ❌ |
| C-6 | Canteen/clinic/housing/transport are production-service, not admin | ⚠ Taxonomy mismatch |
| C-7 | Finished-goods store is a marketing centre | ⚠ |
| C-8 | Variable-length, zero-padded centre codes | ❌ |
| C-9 | Cost centre must equal a responsibility unit | ⚠ `manager_user_id` only |
| C-10 | Shared costs split **at disbursement**, period-end is the fallback | ⚠ Ours is period-end-first |
| C-11 | Full absorption is the recommended costing theory | ❌ Undecided |
| C-12 | Step-down is the statutory method; reciprocal permitted with disclosure | ✅ Already our default |

---

## 12.6 Arabic localisation

| # | Requirement | Status |
|---|---|---|
| L-1 | Account names in Arabic | ✅ `name_ar` throughout; the UAS chart supplies them |
| L-2 | Statements and forms in Arabic | ✅ Verified — an Arabic PDF renders with correct shaping |
| L-3 | RTL presentation | ✅ Panel flips from the locale; `dir` driven by the translation file |
| L-4 | Arabic numerals | ✅ Western digits, which is what the statements themselves use |
| L-5 | Level names in Arabic (حساب مساعد …) | ❌ To add with `account_level` |

➕ **We exceed the standard here.** It assumes an Arabic-only system; ours is bilingual
with a per-user locale, which the standard neither requires nor anticipates.

---

## 12.7 Chapter 10 controls — the standard's own checklist

Assessed literally against printed 531.

### Input controls

| # | Control | Status | Evidence |
|---|---|---|---|
| I-1 | Authorisation required to approve financial transactions | ✅ | `approval_rules`/`requests`/`actions`; V-14; DB CHECK that approver ≠ creator |
| I-2 | Capture only through pre-defined forms | ⚠ | Domain services are the only write path; Filament forms not yet built |
| I-3 | Source documents ordered before processing | ✅ | Gapless sequence at post; documents carry `source_document_no` |
| I-4 | Printed edit report confirming all documents entered and audited before posting | ❌ | Not built. Maps to the "unposted/draft" report |

### Processing controls

| # | Control | Status | Evidence |
|---|---|---|---|
| R-1 | Each input posted **once and only once** | ✅ / ➕ | `idempotency_keys` claimed with `INSERT … ON CONFLICT DO NOTHING` inside the posting transaction — stronger than the standard contemplates |
| R-2 | Level-by-level rollup reconciliation: sum each level, compare with the level above | ⚠ | `gl_balances` materialises balances and a nightly drift check is designed, but **rollup-by-level** specifically depends on the hierarchical chart (D-3) |

### Output controls

| # | Control | Status |
|---|---|---|
| O-1 | Accuracy, completeness, and distribution **only to authorised persons** | ⚠ RBAC and a read-only auditor role exist; report-level distribution control not yet built |

### Files

| # | File | Status |
|---|---|---|
| F-1 | ملف الدليل — chart, all levels, validation authority | ⚠ `accounts` exists; needs the UAS structure |
| F-2 | ملف الأستاذ — one record per account, monthly update, opening balance, budget amount | ⚠ `gl_balances` is the equivalent; budget column belongs to the deferred Ch 8 work |
| F-3 | ملف اليوميات — monthly, one record per document movement | ✅ `journal_entries` + `journal_lines` |
| F-4 | ملف الموازين — trial balance file | ✅ Derived from `gl_balances` |
| F-5 | ملف اليوميات السنوي — annual, retained *"لأغراض التدقيق والرقابة"* | ✅ / ➕ Our ledger is append-only and permanent by design |
| F-6 | Automatic opening-balance roll, issuing `سند قيد فتح الأرصدة` for approval | ⚠ Year-end close designed; the approval voucher is not |
| F-7 | Accept certified input from other computerised systems (payroll, stores) | ⚠ `integration_messages` staging designed, not built |

### ➕ Where we exceed Chapter 10

The chapter specifies no access control, no password policy, no backup and recovery
beyond a `خزن/استرجاع` menu item, no segregation of duties, and no logging. We have
RBAC with nine roles, enforced 2FA for posting and approval roles, an append-only ledger
with a tamper-evidence hash chain, a database-level audit trail that captures changes
made outside the application, and SoD enforced by a database constraint.

A checklist that recorded only shortfalls would misrepresent the position: **on controls,
the build is ahead of the standard, and the gaps are in accounting content.**

---

## 12.8 Auditability

| Requirement | Status |
|---|---|
| Retain ledger movement for several years, easily retrievable (objective 5) | ✅ Append-only, never purged |
| Annual journals file for audit and control | ✅ |
| Prove no entries were deleted | ➕ Rewrite rules block DELETE; hash chain per (entity, journal, year) |
| Who posted, when, who approved | ➕ On the entry, plus a trigger-written audit trail |
| Source document evidence | ➕ Immutable content-addressed attachments |

---

## 12.9 What this means for the plan

1. **Keep the engine, replace the content.** Posting, approvals, audit, immutability and
   sequencing are conformant or better. The chart, statements and cost structure are not.
2. **The chart is the critical path.** D-1 through D-9 all follow from it, as does the
   Chapter 10 rollup control (R-2).
3. **The Value Added statement is the single largest genuinely new capability**, and it
   constrains chart design because its inputs are specific account groups.
4. **Resolve C-2 before building V-07 enforcement**, or the first conformant posting of
   element `٣٥` will be rejected.
5. **Ask whether Al-Idan will run cost centres at all.** The answer selects between two
   different statutory closing accounts and determines whether §12.5 matters.
