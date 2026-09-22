# 11 — دليل النظام المحاسبي الموحد: The Chart of Accounts

**Source:** الفصل الأول, printed pages 16–44 (PDF 17–46) of النظام المحاسبي الموحد،
ديوان الرقابة المالية، الطبعة الثانية ٢٠١١.
**Loaded as:** `database/data/chart-of-accounts.csv` — **682 accounts, 512 postable**.

---

## 11.1 How to read a UAS code

Codes are hierarchical decimal. **The parent is always the code prefix**, one digit per
level, and the digit `0` is never used within a level.

```
2      المطلوبات                            الحساب الإجمالي   level 1
26     الدائنون                             الحساب العام      level 2
266    حسابات دائنة متنوعة                  الحساب المساعد    level 3
2661   تأمينات مستلمة وحسابات التوفير        الحساب الفرعي     level 4
26613  حسابات التوفير                       الحساب الجزئي     level 5
266131 توفير عادي                           الحساب التحليلي   level 6
```

Minimum three levels, maximum six. The committee chose decimal numbering expressly so
that software could **aggregate automatically by prefix** — which is why every report
rolls up on the code rather than on a stored parent column, and why the seeder proves
every parent exists rather than trusting one.

### Sibling numbering is deliberately sparse

There is no `17`, `27`, `233`, `2311`, `328`, and no `371` — the last because
land is not depreciated, so the chart encodes the policy. Other confirmed gaps:
`3351` and `4381` (again land), `33166`, `3368`, `331291`, `331294`, `4394`,
`483221`. **The chart is an enumeration, not a range.** The seeder will not invent an
account that is not printed.

---

## 11.2 The skeleton

### `1` الموجودات — Assets

| Code | Arabic | English |
|---|---|---|
| `11` | الموجودات الثابتة | Fixed assets |
| `12` | مشروعات تحت التنفيذ | Projects under execution |
| `13` | المخزون | Inventory |
| `14` | القروض الممنوحة | Loans granted |
| `15` | الاستثمارات المالية | Financial investments |
| `16` | المدينون | Debtors |
| `18` | النقود | Cash |
| `19` | الحسابات المتقابلة المدينة | Contra accounts - debit |

### `2` المطلوبات — Liabilities

| Code | Arabic | English |
|---|---|---|
| `21` | رأس المال | Capital |
| `22` | الاحتياطيات | Reserves |
| `23` | التخصيصات | Provisions |
| `24` | القروض المستلمة | Loans received |
| `25` | المصارف الدائنة | Banks - credit |
| `26` | الدائنون | Creditors |
| `28` | حساب العمليات الجارية | Current operations account |
| `29` | الحسابات المتقابلة الدائنة | Contra accounts - credit |

### `3` الاستخدامات — Uses

| Code | Arabic | English |
|---|---|---|
| `31` | رواتب وإجور | Salaries and wages |
| `32` | المستلزمات السلعية | Commodity requisites |
| `33` | المستلزمات الخدمية | Service requisites |
| `34` | مقاولات وخدمات | Contracts and services |
| `35` | مشتريات البضائع والأراضي بغرض البيع | Purchases of goods and land for resale |
| `36` | الفوائد واستئجار الأراضي | Interest paid and land rental |
| `37` | الاندثار | Depreciation |
| `38` | المصروفات التحويلية | Transfer expenditure |
| `39` | المصروفات الأخرى | Other expenses |

### `4` الموارد — Resources

| Code | Arabic | English |
|---|---|---|
| `41` | إيراد النشاط السلعي | Commodity production revenue |
| `42` | إيراد النشاط التجاري | Trading revenue |
| `43` | إيراد النشاط الخدمي | Service revenue |
| `44` | إيراد التشغيل للغير | Revenue from operating for third parties |
| `45` | كلفة الموجودات المصنعة داخلياً | Cost of internally manufactured assets |
| `46` | فوائد وإيجارات الأراضي | Interest received and land rents |
| `47` | الإعانات | Subsidies |
| `48` | الإيرادات التحويلية | Transfer revenue |
| `49` | الإيرادات الأخرى | Other revenue |

---

## 11.3 Classes 5–9 — cost centre controls

Chapter 1 lists five further top-level classes but gives them **no breakdown**, stating
they sit outside the دليل proper:

| Code | Arabic | Gloss |
|---|---|---|
| `5` | مراقبة مراكز الإنتاج | Production centres |
| `6` | مراقبة مراكز الخدمات الإنتاجية | Production-service centres |
| `7` | مراقبة مراكز الخدمات التسويقية | Marketing-service centres |
| `8` | مراقبة مراكز الخدمات الإدارية | Administrative-service centres |
| `9` | مراقبة مراكز العمليات الرأسمالية | Capital-operations centres |

Their structure belongs to Chapter 7, and the composite statutory code is
`<control class><use element>` — so `٥٣١` is *salaries and wages, production centres*.
See [13 §13.5](13-uas-redesign.md) for how this is stored and presented.

---

## 11.4 الحسابات المتقابلة — the `19`/`29` contra pairs

A paired memorandum mechanism. Every entry to one leg mirrors on the opposite side of
its partner; each balance closes against its partner; **neither appears within the
balance sheet totals**, being presented below them.

| Debit (`19`) | Credit (`29`) | Purpose |
|---|---|---|
| `191` حركة الإنتاج التام بسعر البيع | `291` مقابل … | Finished production at selling price |
| `192` حسابات الالتزامات المدينة | `292` حسابات الالتزامات الدائنة | Commitments |
| `1921` الإعتمادات المستندية المستلمة | `2921` مقابل … | LCs received |
| `1922` **مقابل** الإعتمادات المستندية الصادرة | `2922` الإعتمادات المستندية الصادرة | LCs issued |
| `1923` خطابات الضمان المستلمة | `2923` مقابل … | Guarantees received |
| `1924` **مقابل** خطابات الضمان الصادرة | `2924` خطابات الضمان الصادرة | Guarantees issued |
| `1925` العقود | `2925` مقابل العقود | Contracts |
| `193` حسابات بالقيمة الرمزية | `293` مقابل … | Nominal-value assets, one dinar each |
| `194` حسابات النتيجة المدينة | `294` حسابات النتيجة الدائنة | Imputed rent and interest |
| `195` موجودات أثرية وفنية | `295` مقابل … | Antiquities and artworks |

**Pair by the trailing digits, never by the word مقابل** — on `1922` and `1924` the
prefix sits on the opposite side from where the pattern would suggest. The seeder links
`contra_pair_code` by digits for exactly this reason.

`1943` and `1944` matter beyond control: their credit twins `2943`/`2944` are
inputs to the Gross Value Added statement.

---

## 11.5 Structural symmetries

The standard mirrors related accounts across classes, and these are useful as validation:

| Uses | Resources / Liabilities | Meaning |
|---|---|---|
| `37` الاندثار | `231` مخصص الاندثار المتراكم | Depreciation vs accumulated |
| `36` فوائد مدينة واستئجار الأراضي | `46` فوائد دائنة وإيجارات الأراضي | Interest and rent, both directions |
| `385` إعانات | `47` الإعانات | Subsidies given vs received |
| `38` المصروفات التحويلية | `48` الإيرادات التحويلية | Transfers |
| `39` المصروفات الأخرى | `49` الإيرادات الأخرى | Other |
| `394` خسائر متوقعة | `235` مخصص خسائر متوقعة | Expected losses vs provision |

All six hold in the loaded chart.

---

## 11.6 Derived attributes — our decisions, not the standard's

The document assigns **neither postability nor normal balance** to any account. Both are
computed; the rules and their justification are in [13 §13.1](13-uas-redesign.md), and
the same caveat is repeated in `database/data/README.md` so nobody downstream mistakes
them for statutory assignments.

---

## 11.7 Transcription integrity

Every code was read from a rendered page image and independently re-read at higher
resolution. The assembled chart passes:

- no duplicate codes; every code matches `^[1-9]{1,6}$`
- every non-root code's prefix present as an account (**0 orphans**)
- `account_level` equals code length for all 682
- no postable account above level 3, and none with children
- the level-2 set matches the printed summary table on pages 17–18 exactly

That last check exists because it caught a real gap: PDF pages 30–31 initially fell
between two transcription ranges, losing `167`, all of `18 النقود` and the whole
`19` branch — 40 accounts. A prefix-parent check cannot detect that, because a wholly
missing subtree orphans nothing. Completeness must be checked against the source's own
summary, not against internal consistency.

Source inconsistencies were **not** silently corrected; they are listed for the auditor
in [15 §15.2](15-uas-inputs-required.md).
