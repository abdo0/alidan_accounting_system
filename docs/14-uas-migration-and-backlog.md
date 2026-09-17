# 14 — Migration, Backlog, Testing and Rollout

**Reads from:** [12 — Gap Analysis](12-uas-gap-analysis.md),
[13 — Redesign](13-uas-redesign.md)

---

## 14.1 The migration is unusually cheap, and there is a deadline on that

Nothing has been posted. The system is on a placeholder chart, on a branch, with 63
tests and no production data. Replacing the chart today is a **schema change plus a
seeder change** — no data migration, no reclassification entries, no restatement of
comparatives.

That stops being true the moment real postings begin. The chart replacement should land
before any production data is entered; everything else in this document can follow.

**Recommendation: freeze the chart first, then continue building outward.**

---

## 14.2 Migration steps

### Phase 0 — chart replacement (no data at risk)

| # | Step | Gate |
|---|---|---|
| 0.1 | Complete Chapter 1 transcription, double-read | Two independent reads agree; all structural validations pass |
| 0.2 | Finance lead reviews a printed Arabic chart | Signed off |
| 0.3 | Alter `account_class`, add `account_level`, code-format constraints | `migrate:fresh` clean |
| 0.4 | Replace `chart-of-accounts.csv`; seeder derives postability and normal balance | Seeder validations pass; account count matches the source |
| 0.5 | Repoint `config/accounting.php` at real UAS codes | Posting engine's 27 tests pass unchanged |
| 0.6 | Add activity columns; re-cut cost-centre taxonomy; seed the UAS centre tree | Tests green |

### Phase 1 — opening balances (when Al-Idan is ready to go live)

Standard opening-balance migration, unchanged in shape from `docs/08` §8.3 but now
mapping onto UAS codes:

| # | Step | Gate |
|---|---|---|
| 1.1 | Obtain the current chart ([15 D-1](15-uas-inputs-required.md)) and map old → UAS code | Every legacy account mapped; no unmapped balance |
| 1.2 | Extract the cutover trial balance from the legacy system | Legacy TB balances |
| 1.3 | Load as an `opening` journal entry through the normal posting path | **New TB equals legacy TB, account by account** — not "within tolerance" |
| 1.4 | Load open AR/AP at document level | Subledger totals equal the migrated control-account balances |
| 1.5 | Load fixed assets with cost and accumulated depreciation | Register agrees to `11`/`231` |
| 1.6 | Load inventory | Valuation agrees to `13` |
| 1.7 | Load prior-year summary per account per period | Every statement's `السنة السابقة` column populates |
| 1.8 | Two dry runs | Second reproduces the first exactly |

**Step 1.3 is the one that matters.** A mapping error there compounds into every
statement forever.

### Mapping notes specific to the UAS

- Equity accounts map into class `2` — there is no equity class.
- Expense accounts map into `3` الاستخدامات by *nature*, not by function. A legacy
  chart organised by department must be re-cut: the department becomes a cost centre and
  the nature becomes the account.
- Revenue maps into `4` الموارد by activity type — سلعي / تجاري / خدمي / تشغيل للغير.
  The split matters because the Value Added statement reads these groups.
- Anything with no UAS equivalent goes to the nearest `…9 أخرى` account with a note, and
  is listed for the controller.

---

## 14.3 Prioritised backlog

Effort in developer-days, optimistic / likely / pessimistic, assuming the developer who
built the engine.

### P0 — before any production posting

| # | Item | O | L | P | Depends on |
|---|---|---|---|---|---|
| 1 | Chapter 1 transcription + double-read + validation | 2 | 3 | 5 | — |
| 2 | `account_class`, `account_level`, code constraints migration | 1 | 1.5 | 2 | 1 |
| 3 | Seeder rewrite: prefix-parent derivation, postability, normal balance | 1.5 | 2 | 3 | 2 |
| 4 | Repoint `config/accounting.php`; fix the 27 engine tests | 0.5 | 1 | 2 | 3 |
| 5 | Activity classification columns + defaulting | 1 | 1.5 | 2 | 2 |
| 6 | Cost-centre taxonomy re-cut + UAS tree seed | 1.5 | 2 | 3 | 2 |
| 7 | V-07 exemption for element `35` (+ `34` production-only) | 0.5 | 0.5 | 1 | 6 |
| | **P0 subtotal** | **8** | **11.5** | **18** | |

### P1 — statutory reporting

| # | Item | O | L | P | Depends on |
|---|---|---|---|---|---|
| 8 | `statement_definitions` / `statement_lines` schema + engine | 3 | 4 | 6 | 2 |
| 9 | الميزانية العامة | 1.5 | 2 | 3 | 8 |
| 10 | حساب الإنتاج والمتاجرة (إنموذج ١) | 2 | 3 | 4 | 8 |
| 11 | حساب الإيرادات والمصروفات (إنموذج ٢) | 1 | 1.5 | 2 | 8 |
| 12 | كشف العمليات الجارية (two stages, `384` split) | 1.5 | 2 | 3 | 8 |
| 13 | كشف التدفق النقدي, UAS form | 2 | 3 | 4 | 8 |
| 14 | **كشف إجمالي القيمة المضافة + توزيعها**, with the reconciliation test | 2 | 3 | 4 | 8 |
| 15 | Arabic statement rendering via mPDF | 1 | 2 | 3 | 9 |
| 16 | الحسابات المتقابلة `19`/`29` posting path + V-19 | 2 | 3 | 4 | 2 |
| | **P1 subtotal** | **16** | **23.5** | **33** | |

### P2 — books, documents and the monthly cycle

| # | Item | O | L | P |
|---|---|---|---|---|
| 17 | Seven journals renamed/recoded to the standard | 0.5 | 1 | 1.5 |
| 18 | Columnar analysis journals as reports | 2 | 3 | 5 |
| 19 | Monthly aggregate entry (`قيد شهري`) at level 2 | 1.5 | 2 | 3 |
| 20 | Level-2 / level-3 trial balances + reconciliation (Ch 10 control R-2) | 1.5 | 2 | 3 |
| 21 | The six prescribed source documents | 3 | 5 | 8 |
| 22 | Five-signature approval chain seeded | 0.5 | 1 | 1.5 |
| 23 | Edit report before posting (Ch 10 control I-4) | 1 | 1.5 | 2 |
| | **P2 subtotal** | **10** | **15.5** | **24** |

### P3 — cost accounting and analytics

| # | Item | O | L | P |
|---|---|---|---|---|
| 24 | Composite `٥٣١`-style code synthesis + distribution grid | 2 | 3 | 5 |
| 25 | Step-down ordering by served-count; receiving set widened | 1.5 | 2 | 3 |
| 26 | Capital-operations centres capitalising via `451` | 1.5 | 2 | 3 |
| 27 | Disbursement-time cost splitting + `مركز وسيط` | 2 | 3 | 5 |
| 28 | Statutory depreciation rates seeded from Chapter 6 | 1 | 2 | 3 |
| 29 | Analytical statements (the subset that applies) | 4 | 7 | 12 |
| | **P3 subtotal** | **12** | **19** | **31** |

**Total P0–P3: 46 / 69.5 / 106 developer-days ≈ 11–26 weeks**, on top of the subledger
work already planned in `docs/08`. P0 alone is **~2–3.5 weeks** and is the part with a
deadline.

---

## 14.4 Dependencies and risks

| Risk | Impact | Mitigation |
|---|---|---|
| A misread digit in the chart | Statutory error propagating into every statement | Double-read; machine-checked prefix-parent, duplicate, format and level validations; anything uncertain listed in `docs/15` not guessed |
| The 2011 edition has been superseded | Entire chart wrong | [15 B-1](15-uas-inputs-required.md) — auditor confirmation before freeze |
| Production postings start before the chart is replaced | Migration becomes expensive and history becomes unreliable | Freeze the chart first; no production data until P0 lands |
| Al-Idan turns out not to use cost centres | ~30 days of P3 is wasted | [15 B-2](15-uas-inputs-required.md) answered before P3 starts |
| Al-Idan is a contractor | A different closing account is needed and P1 items 10–11 are the wrong ones | [15 B-3](15-uas-inputs-required.md) |
| The step-down tie-break reading is wrong | Allocation results differ from what an Iraqi auditor expects | [15 A-7](15-uas-inputs-required.md) — accountant's ruling before item 25 |
| Adopting a public-sector chart confuses private-sector staff | Adoption friction | Public-sector accounts seeded inactive; training covers why they exist |
| Legacy chart cannot be mapped cleanly | Migration stalls | Obtain it early ([15 D-1](15-uas-inputs-required.md)); unmapped items to `…9 أخرى` with a reported list |

---

## 14.5 Test cases

Beyond the existing 63, keyed to the requirements they prove.

### Chart structure
- Every code matches `^[1-9]{1,6}$`; no `0` digit.
- Every code of length > 1 has its prefix present.
- Postable accounts are all level ≥ 3 and childless; no level-1 or level-2 account is postable.
- Known gaps are absent: `17`, `27`, `233`, `2311`, `328`, `371`.
- Account count equals the source count recorded in `docs/11`.
- First digit agrees with `account_class` for all rows.

### Posting
- Element `35` posts **without** a cost centre (the exemption) — and element `31` without one is rejected.
- Element `34` posts only to a production centre.
- A `19`/`29` memo entry requires matching trailing digits; a mismatched pair is rejected.
- A memo entry does not change the balance sheet totals.
- Activity columns default from the header and are overridable per line.

### Statements
- **The Value Added statement reconciles to its distribution statement** — the single
  highest-value test in this set, because it exercises the chart groupings end to end.
- Balance sheet: `1` totals equal `2` totals; contras appear below, not within.
- حساب العمليات الجارية: `384` appears in stage 1 and the rest of `38` in stage 2.
- Cash flow closing cash equals the actual closing balance of `18`.
- Every statement's prior-year column populates from migrated history.
- إنموذج ١ vs إنموذج ٢ selection follows the entity type and cost-centre flag.

### Chapter 10 controls
- Same input posted twice produces one entry (R-1).
- Level-by-level rollup: Σ level 3 equals level 2 equals level 1, per class (R-2).
- Output of a report by an unauthorised role is refused (O-1).

### Cost accounting
- Step-down orders by served-count and allocates to marketing and admin centres too.
- Capital-operations centre costs capitalise via `451`, not into cost of sales.
- Composite code synthesis produces `531` for element `31` in a production centre.

---

## 14.6 Training

| Audience | Content | Duration |
|---|---|---|
| All finance staff | Why the UAS, what changed from the previous chart, the four classes, how codes nest | 0.5 day |
| Accountants | Reading the chart at levels 2–6, posting at level 3+, the memo accounts, activity classification | 1 day |
| Financial controller | The statement set and which applies, close cycle, cost-centre structure and allocation, the alignment checklist | 2 days |
| Cost centre managers | Reading their cost report; what allocated overhead means | 2 hours |
| External auditor | Chart provenance, derivation decisions, where the standard was deviated from and why | 2 hours |

Material in Arabic first, English second — the reverse of the usual order here, because
the chart is Arabic and the account names are the vocabulary.

**A specific point to teach explicitly:** postability is derived, not decreed by the
standard. Accountants will ask why some level-3 accounts accept postings and others do
not, and the answer — that the account has children — should come from training rather
than from trial and error.

---

## 14.7 Rollout

1. **Chart freeze** — P0 complete, finance lead signed off on the printed Arabic chart.
2. **Parallel period** — enter one month of real transactions alongside whatever is used
   today. Compare the trial balance line by line.
3. **Statement dry run** — produce the full statement set for that month and walk it with
   the controller and, ideally, the external auditor.
4. **Opening balance load** — Phase 1 above, with its gates.
5. **Go live** — after two clean parallel cycles, not one.
6. **First close** — run by the finance team unaided, with support available.

Go/no-go criteria: two clean parallel cycles; migration reconciliation signed; the Value
Added statement reconciles; no open P0 or P1 defect; auditor has walked the chart and
raised no blocking objection.

---

## 14.8 Alignment checklist

The formal artefact for governance review is
[12 §12.7](12-uas-gap-analysis.md) — Chapter 10's own controls, assessed item by item,
with the four dimensions where the build exceeds the standard recorded alongside the
gaps. It should be re-run and re-signed at each of the rollout stages above.

Restating the framing, because it matters in a governance context: Al-Idan is private
sector and outside the standard's stated scope. This is voluntary alignment. The
checklist records how closely the system follows a standard the business has chosen to
adopt — it is not, and should not be presented as, a statement of statutory compliance.
