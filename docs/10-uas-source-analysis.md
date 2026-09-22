# 10 — النظام المحاسبي الموحد: Source Analysis & Executive Summary

**Source:** `النظام المحاسبي الموحد` — جمهورية العراق، ديوان الرقابة المالية،
الطبعة الثانية، ٢٠١١ (Republic of Iraq, **Board of Supreme Audit**, 2nd edition, 2011).
553 PDF pages. Supplied by Al-Idan as `projects/3198098106.pdf`.

**Status:** analysis in progress. Chapter 1 (the chart of accounts) is being transcribed
in full; Chapters 4, 5, 7 and 10 have been read and are summarised here.

---

## 10.1 Executive summary

The Unified Accounting System is Iraq's national accounting framework, issued by the
Board of Supreme Audit. The 2011 second edition updates the original 1986-era system
explicitly to align with **International Accounting Standards**, after the legislative
changes of the 2000s (Central Bank of Iraq, the banking and insurance laws, the Iraq
Stock Exchange) obliged a number of institutions to apply them.

It is not a chart of accounts with commentary. It is a complete accounting system in ten
chapters: the chart, an account-by-account explanation, prescribed accounting
treatments, prescribed financial statements, prescribed source documents and books,
statutory depreciation rates, a cost-accounting framework, planning budgets, national
accounts, and — unusually for a document of this vintage — **a chapter specifying how the
system is to be computerised**, including a control checklist.

### Five things that matter most for this project

1. **The chart is structurally different from what we built.** Nine top-level classes
   against our five; capital and reserves are *liabilities*, not equity; expenses are
   الاستخدامات ("uses") and revenues are الموارد ("resources"); and classes `5`–`9` are
   reserved for cost-centre controls. Codes are hierarchical decimal, 1–6 digits, where
   the parent is always the code prefix.

2. **Two attributes we treat as data are not in the document at all.** Neither
   postability nor normal balance is ever stated per account. Both must be *derived*,
   and the derivation recorded as our decision. See §10.4.

3. **It prescribes statements we do not have**, most notably
   **كشف إجمالي القيمة المضافة** — a Gross Value Added statement, with a defined formula
   over specific account groups. There is also a paired off-balance-sheet contra
   mechanism (`19`/`29`) with no equivalent in our design.

4. **Its cost-accounting chapter contradicts a design decision we documented.** We argued
   for cost centres as a dimension and against account-code segmentation; the standard
   integrates them into the chart deliberately. Resolution in
   [13 — Redesign](13-uas-redesign.md).

5. **Al-Idan is private sector, so none of this is legally compulsory.** See §10.2. It is
   worth adopting anyway, for reasons set out there, but the distinction should be stated
   plainly in any governance review.

---

## 10.2 Scope — does it apply to Al-Idan?

Printed page 9 (نطاق تطبيق النظام المحاسبي الموحد) defines the scope:

**Covered**
- الوحدات الاقتصادية الإنتاجية — economic production units, and training centres serving them
- Publishing, printing and distribution establishments, regardless of state support
- Public-sector institutions performing construction, consultancy and laboratory work
- All public tourism-sector establishments
- **جميع الجمعيات التعاونية** — all cooperative societies
- **جميع شركات القطاع المختلط** — all mixed-sector companies

**Expressly exempted**
- الوزارات والدوائر التي تعتبر موازنتها جزءاً من موازنة الدولة الاعتيادية — ministries and
  departments funded through the ordinary state budget
- **المصارف** — banks
- **شركات التأمين** — insurance companies

**Private-sector companies are not named.** Al-Idan is private sector (confirmed with the
client, 2026-09-17), so adopting the UAS is a **voluntary alignment, not a legal
obligation**, and the Board of Supreme Audit's review regime does not attach.

### Why adopt it anyway

- Iraqi finance staff and external auditors know this chart; it is the vocabulary of the
  profession locally. A bespoke chart imposes a translation burden on every hire and
  every audit.
- Dealings with government, mixed-sector and cooperative counterparties are easier when
  the books speak the same structure.
- The 2011 edition is IAS-oriented, so alignment does not mean abandoning international
  comparability.

### What it costs

A number of constructs are public-sector-specific and will never be used by a private
company — `2681` حصة الخزينة العامة (Public Treasury profit share), `47` الإعانات
(state subsidies), `28` حساب العمليات الجارية with the state, `382` contributions to
parent economic units. These are **seeded inactive rather than deleted**: removing them
would misrepresent the standard, and a future change of status would need them back.

**Governance note.** Because this is voluntary, the deliverable in
[12 — Gap Analysis](12-uas-gap-analysis.md) is an **alignment checklist**, not a
compliance certificate. Nothing here should be represented to a third party as statutory
compliance.

---

## 10.3 The document, and how it had to be read

### It has no usable text layer

The PDF was produced in 2011 by `docPrint PDF Driver v2.1 (2008)` from Microsoft Word.
Its embedded fonts use custom encodings with no ToUnicode CMap, so `pdftotext` returns
control characters and mojibake rather than Arabic. Example, from page 1:

```
 M-^@ ^D^H^G ^C^F^E^D^C^B M-^@   ...
```

`tesseract` is installed on this machine but **has no Arabic language data** (`ara`), so
OCR was not available either. The workable route is to **render pages to PNG with
`pdftoppm` and read the images**, which is accurate and was used throughout.

**Consequence for this project:** there is no automated pipeline from this PDF to data.
Every figure in the analysis documents was read by eye from a rendered page, and the
chart of accounts was transcribed and then independently re-read. Anything uncertain is
recorded in [15 — Inputs Required](15-uas-inputs-required.md) rather than guessed.

### Page numbering

The printed page number and the PDF page number differ by an offset that **grows through
the book** as unnumbered divider pages accumulate. Never compute one from the other.

| Section | Printed | PDF | Offset |
|---|---|---|---|
| Introduction | 2 | 3 | +1 |
| Ch 1 — chart of accounts | 16–44 | **17–46** | +2 |
| Ch 4 — financial statements | 255–306 | **260–311** | +5 |
| Ch 5 — documents and books | 307–373 | **313–379** | +6 |
| Ch 7 — cost accounting | 404–444 | **412–452** | +8 |
| Ch 10 — computerisation | 524–532 | **539–547** | +15 |
| Index (فهرست الكتاب) | 533–538 | 548–553 | +15 |

---

## 10.4 Chapter map

| Ch | Title | Printed | Covered here |
|---|---|---|---|
| — | المقدمة — assumptions, principles, features, scope | 1–15 | Yes |
| 1 | **دليل النظام المحاسبي الموحد** — chart of accounts | 16–44 | [11](11-uas-chart-of-accounts.md) |
| 2 | شرح الدليل — account-by-account explanation | 45–118 | [11](11-uas-chart-of-accounts.md) |
| 3 | المعالجة المحاسبية — accounting treatments | 119–254 | [12](12-uas-gap-analysis.md) |
| 4 | القوائم المالية والحسابات الختامية — statements | 255–306 | §10.6 below |
| 5 | المجموعة المستندية والدفترية — documents and books | 307–373 | §10.7 below |
| 6 | جداول نسب اندثار الموجودات الثابتة — depreciation rates | 374–403 | [13](13-uas-redesign.md) |
| 7 | التكاليف — cost accounting | 404–444 | [13](13-uas-redesign.md) |
| 8 | الموازنات التخطيطية — planning budgets | 445–488 | Deferred |
| 9 | الحسابات القومية — national accounts | 489–523 | Out of scope (public-sector statistics) |
| 10 | مكننة النظام — computerisation | 524–532 | §10.8 below |

---

## 10.5 Principles the standard imposes on the software

From المبادئ والأسس (printed 7–8), the bases the drafting committee adopted. Three have
direct engineering consequences:

**Decimal numbering exists to enable automatic rollup.**

> اعتماد **الترقيم العشري** والتبويب المتسلسل المنطقي لحسابات الدليل بهدف تسهيل إستخدام
> الحاسبة الألكترونية في مسك السجلات وبشكل يؤدي إلى **تجميع البيانات تلقائياً وفق تبويبات
> الدليل** بما يخدم التخطيط والمتابعة والرقابة.

Aggregation by code prefix is the standard's *intended* mechanism, not an implementation
convenience. Our seeder should enforce prefix-parent integrity, and our reports should
roll up on it.

**Activity must be classified two ways.**

> التمييز بين النشاط الجاري والنشاط الاستثماري وكذلك التمييز بين النشاط الاعتيادي والنشاط
> الاستثنائي للوحدة.

Current vs investment activity, and ordinary vs exceptional. Our posting line carries
cost centre and project but nothing for either. Both are added — see
[13](13-uas-redesign.md).

**The chart must preserve value-added components.** Classification exists partly so that
the components of القيمة المضافة remain derivable. That is what makes §10.6's Value Added
statement computable, and it constrains any temptation to reshape account groupings for
convenience.

---

## 10.6 Chapter 4 — the prescribed financial statements

Nine outputs. Note that **three alternative closing accounts** exist and the entity uses
the one matching its type:

| # | Statement | Used by |
|---|---|---|
| 1 | الميزانية العامة — general balance sheet | All |
| 2 | حساب الإنتاج والمتاجرة والأرباح والخسائر والتوزيع (إنموذج ١) | Industrial, commercial, and service companies **that have a cost system and cost centres** |
| 3 | حساب الإيرادات والمصروفات والتوزيع (إنموذج ٢) | Service companies **without** a cost system |
| 4 | حساب الأرباح والخسائر للتعهدات والمقاولات المنجزة | Contracting companies |
| 5 | كشف العمليات الجارية | All |
| 6 | كشف التدفق النقدي | All |
| 7 | **كشف إجمالي القيمة المضافة** | All |
| 8 | **كشف توزيع إجمالي القيمة المضافة** | All |
| 9 | ٢٦ كشوفات تحليلية — 26 analytical statements | All |

**Universal column frame**, right to left:
`رقم الكشف | رقم الدليل المحاسبي | أسم الحساب | السنة الحالية/دينار | السنة السابقة/دينار`

Every statement is dated `للسنة المالية المنتهية في ٣١/١٢/٢٠__`, continuation pages are
headed `تابع/`, and the contra accounts `19`/`29` are presented **below** each balance
sheet total, outside it.

### The Value Added statement, in full

`كشف إجمالي القيمة المضافة بسعر تكلفة عناصر الإنتاج` (printed 269):

```
(1) الموارد          = 41 + 42 + 43 + 44 + 45 + 2943 + 2944
(2) مستلزمات الإنتاج  = 32 + 33 + 34 + 35
(3) إجمالي القيمة المضافة بسعر السوق          = (1) − (2)
                                               − 384 (الضرائب والرسوم غير المباشرة)
                                               + 47  (الإعانات)
(4) إجمالي القيمة المضافة بسعر تكلفة عناصر الإنتاج
```

Wages (`31`) and depreciation (`37`) are deliberately **excluded** from inputs — they are
*components* of value added, not deductions from it. The distribution statement
(printed 270) re-derives the same total from the income side:

```
عائد العمل (31, incl. in-kind benefits per analytical statement 20)
+ صافي الفوائد      = 361 − 461
+ صافي إيجار الأراضي = 362 − 462
+ الاندثارات (37)
+ فائض العمليات
= إجمالي القيمة المضافة بسعر تكلفة عناصر الإنتاج
```

The two statements must reconcile. This is a genuine, computable requirement and it is
entirely absent from our current design.

**Note for implementation:** analytical statement #7 hard-codes an ageing bucket
`قبل سنة ٢٠٠٣`. That is a legacy artefact of the 2011 edition; it should be a configurable
cut-off in our implementation, and the deviation recorded.

---

## 10.7 Chapter 5 — documents and books

### The document-control programme (printed 307)

Six mandatory rules, which read as a control checklist we can assess ourselves against:

1. More than one copy of each document, for different purposes and sections, with the
   original distinguishable from the copies.
2. **ترقيم المستندات واستعمالها بالتسلسل** — documents numbered and used in sequence, so
   that a missing one is readily identifiable and the scope for manipulation is reduced.
3. The transaction date recorded on every document.
4. Every document carries the unit's name and address.
5. Every document carries a clear title showing its purpose.
6. A defined retention method: secure, locked, and readily retrievable.

Rule 2 is exactly what our gapless sequencing and gap-detection report provide.

### The six source documents (printed 309–318)

`وصل قبض` (receipt, 3 copies, cashier's signature) · `مستند الصرف` (payment voucher,
**five signatures**: المنظم، المحاسب، المدقق، مدير الحسابات، المدير العام) ·
`مستند قيد اليومية` (journal voucher, one unified form typed 3–8 by journal, five
signatures ending رئيس الدائرة) · `قائمة بيع نقداً/على الحساب` (sales invoice) ·
`مستند صرف نثرية` (petty cash) · `كشف مصروفات نثرية` (petty cash statement).

The payment and journal vouchers carry a **digit-by-digit coding grid** — chart levels
3/4/5 and cost-centre levels 5–9 as separate cells. The form is literally the account
code broken into its levels.

### The seven journals (printed 319–340)

`دفتر اليومية العام` plus six subsidiaries: المقبوضات، المدفوعات، المشتريات، الإيرادات،
الصادر المخزني، الاستخدامات. Each is a **columnar analysis journal** with a fixed set of
account columns at level 3 and a residual `حسابات أخرى` field, and each produces a
**monthly aggregate entry** (قيد شهري) posted to the general journal at level 2.

Only `سجل اليومية العامة` and `سجل الأستاذ العام` are **ملزمة** (mandatory); the rest may
be merged, split, or replaced by periodic statements.

### The posting-level rule

> التسجيل … مبتدئة التسجيل على **المستوى الثالث** … يُستخرج قيد شهري على المستوى الثالث،
> يُسجل في سجل اليومية المركزي على **المستوى الثاني**

Entry at level 3 or deeper; monthly aggregation to level 2 in the central journal;
monthly trial balances at levels 2 and 3 with reconciliation between them.

---

## 10.8 Chapter 10 — the standard's own computerisation requirements

This chapter is effectively a specification for software like ours, written in 2011. Its
control list is reproduced in full in [12](12-uas-gap-analysis.md) as our alignment
checklist. The headlines:

**Five objectives** — classify and record transactions per UAS rules **and verify their
correctness**; provide periodic and on-demand statements such as the trial balance;
produce monthly and annual results **per UAS forms**; compare against the planning
budget; and **retain ledger movement for several years** with easy retrieval.

**Three bases** — bind to the chart of accounts as *"العمود الفقري"* (the backbone);
produce the journals and ledger monthly **in sequence**; roll opening balances forward
**automatically**, issuing a `سند قيد فتح الأرصدة` for approval; and accept data from
other computerised systems (payroll, stores) as inputs, posting them automatically once
certified.

**Five permanent files** — chart, ledger, monthly journals, trial balances, and an
**annual journals file** retained *"لأغراض التدقيق والرقابة"*.

**Controls, in three families** — input (authorisation, prescribed forms only, ordered
source documents, an edit report confirming completeness before posting); processing
(**each input posted once and only once**, and a **level-by-level rollup reconciliation**
where each chart level is summed and compared with the level above); and output
(accuracy, completeness, and distribution **only to authorised persons**).

Notably absent from the chapter: access control and passwords, backup and recovery
(beyond a `خزن/استرجاع` component), segregation of duties, and logging. Our build exceeds
the standard on all four — worth stating in the gap analysis, since a checklist that only
records shortfalls misrepresents the position.

---

## 10.9 Open items

Carried to [15 — Inputs Required](15-uas-inputs-required.md). The most significant:

- Confirmation from Al-Idan's auditor that the **2011 second edition is current** and has
  not been amended or superseded.
- Printed inconsistencies in the source itself, to be confirmed rather than silently
  corrected: `41` and `31` are named differently in the summary table than in the detail
  list; the cost-distribution grid prints `٥٣٣` where `٦٣٣` is expected; the monthly
  stores entry prints `٣٢٤` twice where one should be `٣٢٣`; Chapter 10's component
  numbering skips ثامناً.
- Whether cost-centre group `٦٤` is المشتريات — it is skipped in the sample directory but
  appears as a column in the cost register.
