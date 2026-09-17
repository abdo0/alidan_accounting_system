# Chart of accounts data

## Provenance

`chart-of-accounts.csv` is the chart of accounts of the **Iraqi Unified Accounting
System**:

> **النظام المحاسبي الموحد**
> جمهورية العراق — ديوان الرقابة المالية (Republic of Iraq, Board of Supreme Audit)
> الطبعة الثانية، ٢٠١١ (Second edition, 2011)
> الفصل الأول — دليل النظام المحاسبي الموحد, printed pages 16–44 (PDF pages 17–46)

682 accounts. Transcribed from page images, because the source PDF has no usable text
layer — see `docs/10-uas-source-analysis.md` §10.3. Each code was read, then
independently re-read, and the assembled chart is validated against the standard's own
structural rules before it is imported.

**Al-Idan is a private-sector company and is outside the standard's stated scope**
(printed page 9 covers public-sector units, cooperative societies and mixed-sector
companies, and expressly exempts banks and insurers). Adopting this chart is a
*voluntary alignment*, recorded as a decision — not statutory compliance. Public-sector
accounts that a private company will never use are retained rather than deleted, because
removing them would misrepresent the standard.

## What the standard does NOT supply

Two columns are **derived**, not read from the source, because the document states
neither anywhere in Chapter 1:

- **`is_postable`** — derived as `account_level >= 3 AND the account has no children`,
  from the prose rule that posting runs *"ابتداءً من المستوى الثالث"* to the leaf.
- **`normal_balance`** — derived from the class (`1`,`3` debit-natured; `2`,`4`
  credit-natured), with explicit overrides where an account name says otherwise
  (`163 حسابات جارية مدينة` against `263 … دائنة`, and so on).

Do not represent either as the standard's own assignment. The reasoning is recorded in
`docs/13-uas-redesign.md` §13.1.

## Structure

Codes are hierarchical decimal, **1–6 digits, where the parent is the code prefix** and
the digit `0` is never used within a level. The committee adopted this numbering
expressly so that software could aggregate automatically by prefix — so the seeder
enforces it rather than trusting it.

| Level | Arabic | Gloss |
|---|---|---|
| 1 | الحساب الإجمالي | Aggregate |
| 2 | الحساب العام | General |
| 3 | الحساب المساعد | Subsidiary |
| 4 | الحساب الفرعي | Branch |
| 5 | الحساب الجزئي | Partial |
| 6 | الحساب التحليلي | Analytical |

Classes: `1` الموجودات · `2` المطلوبات (capital and reserves live here; there is no
equity class) · `3` الاستخدامات · `4` الموارد. Classes `5`–`9` are reserved for
cost-centre controls and are given no breakdown in Chapter 1 — they belong to Chapter 7.

**Sibling numbering is deliberately non-contiguous.** There is no `17`, `27`, `233`,
`2311`, `328`, and no `371` because land is not depreciated. The chart is an explicit
enumeration: the seeder will not create an account that is not printed in the source,
and rejects one whose prefix-parent is missing.

## Columns

| Column | Meaning |
|---|---|
| `code` | UAS account code, 1–6 digits of 1–9. Unique. Never reused |
| `name_ar` | The account name as printed. Canonical |
| `name_en` | English gloss, supplied for levels 1–2 only (report headings). Blank deeper, where the Arabic name stands |
| `account_class` | `asset`, `liability`, `use`, `resource`, or a `cc_*` control class |
| `account_level` | 1–6. Always equals the code length; enforced by a DB constraint |
| `normal_balance` | `D`/`C`. **Derived** — see above |
| `statement` | `BS` الميزانية · `RESULT` حسابات النتيجة · `MEMO` the 19/29 contras, shown below the balance sheet totals · `CC` |
| `cash_flow_class` | `operating`/`investing`/`financing`/`none`, feeding كشف التدفق النقدي |
| `is_postable` | **Derived** — see above |
| `is_control_account` | Whole controlled branch, heading and leaves alike |
| `control_subledger` | `receivables`, `payables`, `inventory`, `fixed_assets` |
| `requires_cost_centre` | Uses only, and **never element `35`**, which the standard does not allocate to a centre |
| `is_reconcilable` | Cash (`18`) and the contra classes |

## Regenerating

The CSV is assembled from the transcription fragments by
`scratchpad/assemble_chart.py`, which validates before writing. To load:

```bash
php artisan db:seed --class=Database\Seeders\ChartOfAccountsSeeder
```

The seeder re-validates independently and refuses a chart that breaks any structural
rule, rather than importing a broken one.

## Open items

Source ambiguities that need Al-Idan's auditor to rule on — including whether the 2011
edition is still current — are listed in `docs/15-uas-inputs-required.md`. None has been
silently corrected.
