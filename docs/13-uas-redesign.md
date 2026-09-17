# 13 — Redesign for the Unified Accounting System

**Reads from:** [10 — Source Analysis](10-uas-source-analysis.md),
[12 — Gap Analysis](12-uas-gap-analysis.md)
**Applies to:** branch `build/accounting-system`. Nothing has been posted, so every
change below is a schema change rather than a data migration.

---

## 13.1 The chart of accounts

### Schema changes to `accounts`

```sql
-- The UAS classes. Equity disappears: capital and reserves are liabilities.
ALTER TABLE accounts DROP CONSTRAINT accounts_class_valid;
ALTER TABLE accounts ADD CONSTRAINT accounts_class_valid CHECK (account_class IN (
    'asset',            -- 1 الموجودات
    'liability',        -- 2 المطلوبات (includes capital and reserves)
    'use',              -- 3 الاستخدامات
    'resource',         -- 4 الموارد
    'cc_production',    -- 5 مراقبة مراكز الإنتاج
    'cc_prod_services', -- 6 مراقبة مراكز الخدمات الإنتاجية
    'cc_marketing',     -- 7 مراقبة مراكز الخدمات التسويقية
    'cc_admin',         -- 8 مراقبة مراكز الخدمات الإدارية
    'cc_capital'        -- 9 مراقبة مراكز العمليات الرأسمالية
));

-- The level is meaningful in the UAS and has a name the finance team uses.
ALTER TABLE accounts ADD COLUMN account_level smallint;
ALTER TABLE accounts ADD CONSTRAINT accounts_level_valid
    CHECK (account_level BETWEEN 1 AND 6);
-- The level IS the code length. Enforced, not merely documented.
ALTER TABLE accounts ADD CONSTRAINT accounts_level_matches_code
    CHECK (account_level = length(code));
-- Digit 0 is never used within a level.
ALTER TABLE accounts ADD CONSTRAINT accounts_code_digits
    CHECK (code ~ '^[1-9]{1,6}$');
```

`statement` is repurposed to the UAS statement families: `BS` (الميزانية العامة),
`RESULT` (حسابات النتيجة), `CC` (cost-centre control, appears in neither), `MEMO`
(the `19`/`29` contras, which appear below the balance sheet totals rather than in them).

`statutory_code` inverts meaning — the UAS code is now `code`, and `statutory_code` is
freed to carry an IFRS-style mapping should a lender ever ask for one.

`parent_code` is **dropped from the CSV format**. The parent is the code prefix; a
separate column can only disagree with it.

### Derivations the seeder performs

Neither attribute exists in the source. Both are computed, and this is recorded as our
decision rather than presented as the standard's:

```php
// Postability: the standard says posting runs "ابتداءً من المستوى الثالث" to the leaf.
$isPostable = $level >= 3 && ! $hasChildren;

// Normal balance: not stated per account anywhere in Chapter 1.
// Derived from class, with the lexical exceptions listed explicitly in the CSV.
$normalBalance = match ($class) {
    'asset', 'use' => 'D',
    'liability', 'resource' => 'C',
    default => 'D',   // cost-centre controls are debit-natured
};
```

Known lexical exceptions carried as explicit overrides: `163 حسابات جارية مدينة` vs
`263 … دائنة`; `361 فوائد مدينة` vs `461 فوائد دائنة`; `1250` allowance for doubtful
debts (asset, credit-natured); the `231`–`239` provisions; `1800`-series accumulated
depreciation equivalents under `231`.

### Structural validation

The seeder refuses a chart that violates any of these:

1. No duplicate codes.
2. Every code of length > 1 has its prefix present as an account.
3. Every code matches `^[1-9]{1,6}$`.
4. Every postable account is at level ≥ 3.
5. No account has children *and* is postable.
6. Class digit (first character) matches `account_class`.
7. Deliberate gaps are preserved — the chart is an enumeration, and any account not
   printed in the source is **not** created.

Rule 2 is the standard's own structural rule
([10 §10.5](10-uas-source-analysis.md)) turned into an assertion.

---

## 13.2 Activity classification

Required by المبادئ والأسس. Two nullable columns, defaulted from the document header:

```sql
ALTER TABLE journal_lines
    ADD COLUMN activity_type   varchar(12),   -- current | investment
    ADD COLUMN activity_nature varchar(12);   -- ordinary | exceptional

ALTER TABLE journal_lines ADD CONSTRAINT jl_activity_type_valid
    CHECK (activity_type IS NULL OR activity_type IN ('current','investment'));
ALTER TABLE journal_lines ADD CONSTRAINT jl_activity_nature_valid
    CHECK (activity_nature IS NULL OR activity_nature IN ('ordinary','exceptional'));
```

Arabic labels: `جاري` / `استثماري`, and `اعتيادي` / `استثنائي`.

These are not decorative. كشف العمليات الجارية separates current from non-current
activity, and the cash flow statement has a line
`التدفق النقدي عن الفقرات غير العادية` keyed to accounts `165`/`265` — the exceptional
items. Without the columns, both statements have to infer intent from account codes,
which works until it does not.

---

## 13.3 الحسابات المتقابلة — the contra mechanism

Classes `19` and `29` are paired memorandum accounts. Every entry to one leg is mirrored
on the opposite side of its partner; each balance closes against its partner; **neither
appears within the balance sheet totals**, being presented below them.

```sql
-- Pairing is by trailing digits (19X ↔ 29X), NOT by the word مقابل, which appears
-- on different sides in different pairs (see 1922/1924).
ALTER TABLE accounts ADD COLUMN contra_pair_code varchar(30);
```

Posting implications:

- A `MEMO` entry must touch **exactly one `19x` and one `29x` account whose trailing
  digits match**. A new validation rule, V-19.
- `gl_balances` carries them normally, but every balance-sheet report **excludes
  `statement = 'MEMO'` from the totals and lists them beneath**.
- The year-end close must close each leg against its partner rather than to retained
  earnings.

What they are used for, and why it is worth having: letters of credit and guarantees
issued and received (`192`/`292`), assets carried at a nominal one dinar (`193`/`293`),
imputed rent and imputed interest that feed the value-added computation (`194`/`294`),
and antiquities and artworks (`195`/`295`). The commitment tracking alone is a real
capability — off-balance-sheet exposure is otherwise invisible.

---

## 13.4 Financial statements

A statement definition becomes data rather than code, because there are nine of them,
three are mutually exclusive by entity type, and the line-to-account-group mappings are
prescribed:

```sql
CREATE TABLE statement_definitions (
    id            bigserial PRIMARY KEY,
    code          varchar(30) NOT NULL UNIQUE,   -- BALANCE_SHEET, GVA, GVA_DISTRIBUTION…
    name_ar       varchar(200) NOT NULL,
    name_en       varchar(200) NOT NULL,
    form_no       varchar(10),                   -- إنموذج رقم (١) / (٢)
    entity_types  text[],                        -- industrial | commercial | service | contracting
    requires_cost_centres boolean NOT NULL DEFAULT false,
    is_active     boolean NOT NULL DEFAULT true
);

CREATE TABLE statement_lines (
    id                bigserial PRIMARY KEY,
    statement_id      bigint NOT NULL REFERENCES statement_definitions(id),
    sequence          integer NOT NULL,
    label_ar          varchar(300) NOT NULL,
    label_en          varchar(300),
    line_type         varchar(20) NOT NULL,      -- header|accounts|formula|subtotal|total|note
    account_codes     text[],                    -- the chart codes rolled up, by prefix
    formula           text,                      -- for computed lines, e.g. 'L10 - L20'
    sign              smallint NOT NULL DEFAULT 1,
    analytical_ref    smallint,                  -- the كشف رقم cross-reference column
    indent_level      smallint NOT NULL DEFAULT 0
);
```

`account_codes` holds **prefixes**, so a line naming `41` picks up `411`…`417` and every
level below. That is the standard's own aggregation rule doing the work.

### The Value Added statement, as data

```
GVA_MARKET  = Σ(41,42,43,44,45,2943,2944) − Σ(32,33,34,35)
GVA_FACTOR  = GVA_MARKET − Σ(384) + Σ(47)
```

And its distribution, which must reconcile to the same figure:

```
عائد العمل        = Σ(31) + in-kind benefits (analytical statement 20)
صافي الفوائد       = Σ(361) − Σ(461)
صافي إيجار الأراضي  = Σ(362) − Σ(462)
الاندثارات        = Σ(37)
فائض العمليات     = GVA_FACTOR − the above
```

A test asserts the two statements agree. That reconciliation is the whole point of
producing both.

### Statement selection

`حساب الإنتاج والمتاجرة` (إنموذج ١) applies to industrial, commercial and service
companies **with** a cost system; `حساب الإيرادات والمصروفات` (إنموذج ٢) to service
companies **without** one; the contracting P&L to contractors. The entity's type and its
`uses_cost_centres` flag select which is produced. This is why
[15 B-2 and B-3](15-uas-inputs-required.md) are blocking questions.

---

## 13.5 Cost accounting

### The conflict, and how it resolves

`docs/05` rejected account-code segmentation for cost centres. The UAS uses it
deliberately — *مبدأ الإندماج*, integration rather than separation — and `٥٣١` is a real
account meaning "salaries and wages, production centres".

My earlier reasoning does not apply here because the segmentation is **bounded**: five
control groups × ~nine use elements is a 45-cell grid, not a combinatorial explosion,
and the centre detail lives *below* the control account rather than multiplying it.

**Resolution: store the dimension, present the composite.** Postings keep
`cost_centre_id`; a mapping layer emits the `٥٣١`-style composite codes and the
prescribed `كشف توزيع الإستخدامات على مراقبات مراكز التكاليف` grid. We get queryability
and avoid deep-level explosion; the standard gets its presentation.

### Cost-centre taxonomy

```sql
ALTER TABLE cost_centres DROP CONSTRAINT cost_centre_type_valid;
ALTER TABLE cost_centres ADD CONSTRAINT cost_centre_type_valid
    CHECK (cost_centre_type IN ('production','prod_service','marketing','admin','capital'));

-- Ties the centre to its control class 5-9, which is what makes the composite code
-- derivable.
ALTER TABLE cost_centres ADD COLUMN control_class smallint;
ALTER TABLE cost_centres ADD CONSTRAINT cc_control_class_valid
    CHECK (control_class BETWEEN 5 AND 9);

-- The standard requires a centre to BE a responsibility unit.
ALTER TABLE cost_centres ADD COLUMN responsibility_unit varchar(150);

-- Codes are variable length and may widen to two digits with a leading zero past nine
-- members, so the fixed-width assumption is dropped.
ALTER TABLE cost_centres ADD COLUMN code_segments text[];
```

Mapping: production → 5, prod_service → 6, marketing → 7, admin → 8, capital → 9.

**Placements that differ from intuition and must be seeded correctly:** canteen, clinic,
housing and staff transport are `6` (production-service), *not* administrative; the
finished-goods store is `7` (marketing) while raw-material, spare-part and packaging
stores are `6`.

### V-07 must gain an exception

Element `٣٥` (مشتريات البضائع والأراضي بغرض البيع) is **never** allocated to a cost
centre — it goes straight to the trading account and is added back when reconciling total
uses. Element `٣٤` goes only to production.

```php
// In PostingValidator::checkDimensions()
// The standard exempts 35 from cost-centre classification entirely. A blanket
// "uses require a cost centre" rule would reject conformant behaviour.
if (str_starts_with($account->code, config('accounting.cost_centre_exempt_prefixes'))) {
    continue;
}
```

This is exactly the kind of rule that is cheap to add now and produces a baffling
rejection in month two if forgotten.

### Allocation

- **Step-down (التوزيع التنازلي) is the statutory method** and is already our default.
- Ordering is by **the count of centres served**, not cost magnitude. `allocation_rules`
  gains an `ordering_basis` of `served_count` | `sequence` | `amount`, defaulting to
  `served_count`.
- The receiving set is **production, marketing, administrative and capital-operations**
  centres plus unallocated service centres — not production alone.
- Reciprocal (التبادلي) stays available. The standard ranks it best in theory and rejects
  it only for manual effort — an objection software removes — but it requires the
  disclosure the standard mandates for non-default methods.
- Arabic UI naming: direct allocation is **التوزيع الإفرادي**, never التوزيع المباشر,
  which reads as *direct cost*. A fourth method, **التوزيع الإجمالي** (single pooled
  rate), should be added for completeness.
- **Shared costs are split at disbursement**, on the voucher itself, via the prescribed
  `إستمارة توزيع الإستخدامات`. Period-end redistribution through a `مركز وسيط` is the
  documented fallback, not the primary path — which inverts our current design.

---

## 13.6 Depreciation

Chapter 6 supplies statutory rates under Financial Instruction No. 11 of 1988, by asset
type. These replace the invented defaults on `asset_categories`, and the source is
recorded per row so the basis is auditable. Note `37` starts at `372`: there is no `371`
because land is not depreciated — the chart encodes the policy.

---

## 13.7 Documents, journals and the monthly cycle

- The six prescribed source documents become the AR/AP/cash document set, with the
  standard's field layouts and the five-signature approval chain on payment and journal
  vouchers.
- The seven journals are renamed and recoded to the standard's set.
- The columnar analysis journals are produced as **reports**, not storage. The standard
  describes a paper register; the same content is a pivot over `journal_lines`, and
  rendering it lets us produce it at any level.
- The monthly aggregate entry (`قيد شهري`) at level 2 and the level-2/level-3 trial
  balances with reconciliation between them are new scheduled work — and they are also
  what satisfies Chapter 10's processing control R-2.

---

## 13.8 What does not change

Worth stating, because it is most of the engine: double-entry enforcement, the posting
validator's structure, gapless sequencing, idempotency, the approval workflow, the audit
trail, immutability and the hash chain, attachments as evidence, RBAC, bilingual UI and
RTL, and the `gl_balances` materialisation strategy. All of it is conformant or ahead of
the standard ([12 §12.7](12-uas-gap-analysis.md)).

The work is replacing accounting content, not rebuilding accounting machinery.
