# 15 — Inputs Required from Al-Idan

Everything below is blocking or risk-bearing. Items are ordered by how much downstream
work they gate. Nothing here is a nice-to-have.

---

## 15.1 Blocking — needed before the chart is frozen

| # | What we need | Why it blocks | Who |
|---|---|---|---|
| B-1 | **Confirmation that the 2011 second edition is current** and has not been amended, superseded or supplemented by a later Board of Supreme Audit circular | The entire chart, the statements and the depreciation rates come from this edition. If it has been amended we would be encoding a superseded standard | External auditor |
| B-2 | **Does Al-Idan operate cost centres, or will it?** | Selects between two different statutory closing accounts — إنموذج ١ (with cost system) and إنموذج ٢ (service companies without one) — and determines whether the whole of Chapter 7 applies | Financial controller |
| B-3 | **Which activity is Al-Idan's primary one** — industrial, commercial, service, or contracting? | Contracting companies use a third closing account entirely (حساب الأرباح والخسائر للتعهدات والمقاولات المنجزة, a per-project matrix). The revenue class `41`–`44` branch used also follows from this | Financial controller |
| B-4 | **Confirmation of the decision to adopt the UAS voluntarily**, recorded formally | Al-Idan is private sector and outside the standard's stated scope. The decision should be minuted rather than assumed, because it commits the business to a public-sector chart | Sponsor |

---

## 15.2 Source ambiguities — confirm rather than let us guess

The printed source contains inconsistencies. We have **not** silently corrected any of
them. Each needs a ruling.

| # | Where | The problem | Our reading |
|---|---|---|---|
| A-1 | Printed 16/18 vs the detail list | Account `41` is *إيراد نشاط الإنتاج السلعي* in the summary table but *إيراد النشاط السلعي* in the detail list. `31` is *الرواتب والأجور* vs *رواتب وإجور* | Treat the **detail list** as canonical — it is the chart proper |
| A-2 | Printed 30 | Code `233` appears to be absent between `232` and `234` | Believed a genuine gap, but a statutory chart warrants a second opinion |
| A-3 | Printed 417 (cost distribution grid) | The service-centres column for element `٣٣` prints `٥٣٣`, where the pattern gives `٦٣٣` | Believed a typographical error |
| A-4 | Printed 340 (monthly stores entry) | Code `٣٢٤` printed twice, for both الأدوات الإحتياطية and مواد التعبئة والتغليف | `٣٢٣` is almost certainly intended for the first |
| A-5 | Printed 418–419 | Cost-centre group `٦٤` is skipped in the sample service-centre directory, yet appears as a column in the Method-2 cost register. Is `٦٤` المشتريات? | Probable but not stated |
| A-6 | Printed 529 | Chapter 10's component list skips ثامناً (goes سابعاً → تاسعاً) | Believed a printing error, no content lost |
| A-7 | Printed 420 | The step-down tie-break sentence — «في حالة تساوي عدد المراكز يجري توزيع تكاليفه مرة أخرى بحصته من تكاليف المراكز الأخرى» — is ambiguous in the original | We read it as a partial second pass, which is neither standard step-down nor reciprocal. Needs an accountant's ruling before the allocation engine is built |
| A-8 | Analytical statement 7 | Ageing buckets hard-code `قبل سنة ٢٠٠٣` | We propose a configurable cut-off and will record the deviation |

---

## 15.3 Configuration decisions

| # | Decision | Default we will apply if you do not specify |
|---|---|---|
| C-1 | Which of the 26 analytical statements are actually required | Build the ones fed by modules we have; defer the rest |
| C-2 | Costing theory | Full absorption (نظرية التكاليف الكلية) — the standard's own recommendation |
| C-3 | Overhead absorption base (six are permitted) | Per cost centre rather than one plant-wide rate, which the standard prefers for control |
| C-4 | Which public-sector accounts to hide from the UI | All of them seeded inactive; the finance lead reviews the list |
| C-5 | Fiscal year | Calendar year, as the statements assume (`٣١/١٢`) |
| C-6 | Depreciation rates | Chapter 6 statutory rates (Financial Instruction No. 11 of 1988) unless you direct otherwise |

---

## 15.4 Data and access we need

| # | Item | Purpose |
|---|---|---|
| D-1 | **Current chart of accounts**, whatever form it exists in — spreadsheet, legacy system export, or the accountant's working list | Mapping old → UAS codes is the core of the migration. Without it there is no migration plan, only a fresh start |
| D-2 | **Opening trial balance** at the intended cutover date | Non-negotiable for go-live |
| D-3 | **Open AR and AP items** at document level | Needed for ageing and collection from day one |
| D-4 | **Fixed asset register** — cost and accumulated depreciation by asset | Needed to compute future depreciation on the statutory rates |
| D-5 | **Inventory quantities and values** at cutover | Needed for cost of sales |
| D-6 | Prior-year figures, one line per account per period | Every statement has a `السنة السابقة` column. Without this it is blank in year one |
| D-7 | Master data: customers, vendors, items, employees | Operation |
| D-8 | **Organisation chart** | The standard requires each cost centre to correspond to a responsibility unit |
| D-9 | Sample of each source document currently in use | To conform our forms to what staff already recognise |
| D-10 | Bank account list and the latest statements | First reconciliation |

---

## 15.5 People we need access to

| Role | For | Expected time |
|---|---|---|
| Financial controller | Chart review and freeze, cost-centre structure, allocation bases, statement selection | ~1 day/week during analysis; 3–5 days concentrated at chart freeze |
| External auditor | B-1, and the source ambiguities in §15.2 | A half-day review |
| Chief accountant / senior accountant | Document formats, journal usage, current practice | 2–3 days |
| IT / infrastructure owner | Deployment, backup, the least-privilege database role | 1 day |
| Sponsor | B-4, and the scope decision on Chapters 8 and 9 | 2 hours |

---

## 15.6 Open questions we cannot resolve from the document

1. **Is there an Iraqi legal instrument** defining *الوحدات الملزمة بالنظام* beyond the
   scope section? Chapter 7 asserts obligation without citing the instrument. Relevant
   only if Al-Idan's status changes.
2. **Tax treatment.** The standard gives account `384` الضرائب والرسوم but not rates,
   filing formats or deadlines. Current Iraqi corporate income tax, withholding and
   social security rates must come from a practitioner — we will not encode rates from
   memory.
3. **Whether Al-Idan will ever need the national accounts statements** (Chapter 9). Out
   of scope today; relevant if the entity becomes mixed-sector.
4. **Retention period** required by Iraqi law for books and vouchers. The standard
   requires retention *"لعدة سنوات"* without specifying; our design retains permanently,
   which is safe but the legal minimum should be known.
