# Chart of accounts data

## ⚠ `chart-of-accounts.csv` is an UNVERIFIED PLACEHOLDER

The agreed chart of accounts for this system is the **Iraqi Unified Accounting
System** (النظام المحاسبي الموحد), and the official code list is to be supplied by
Al-Idan's finance lead or tax adviser.

The file currently in this directory is **not** that chart. It is a conventional
operational chart, structured exactly like the one in `docs/03-data-model.md` §3.1,
included so that the posting engine, the reports and the test suite have something
to run against while the official list is obtained. **Do not treat these codes as
statutory.**

## Replacing it with the official chart

The seeder is data-driven; swapping the chart is a data change, not a code change.

1. Produce a CSV with exactly these columns:

   | Column | Meaning |
   |---|---|
   | `code` | Account code. Unique. Never reused, even after an account is closed. |
   | `name_en`, `name_ar` | Account title in each language. |
   | `parent_code` | Parent account's code, or blank for a top-level heading. |
   | `account_subtype` | Optional refinement: `cash`, `bank`, `receivable`, `payable`, `inventory`, `fixed_asset`, `accum_depreciation`, `prepayment`, `accrual`, `tax`, `suspense`… Drives rules such as "an adjusting entry never touches cash". |
   | `account_class` | `asset`, `liability`, `equity`, `revenue`, `cost_of_sales`, `expense`, `other_income`, `other_expense`, `tax`, `clearing`, `statistical`. |
   | `normal_balance` | `D` or `C`. Contra accounts oppose their class — accumulated depreciation is an asset with a `C` normal balance. |
   | `statement` | `BS`, `PL`, `SOCE` or `NONE`. |
   | `cash_flow_class` | `operating`, `investing`, `financing`, `none`, or blank. Drives the cash flow statement. |
   | `is_postable` | `1` for leaves, `0` for headings. Only leaves accept postings. |
   | `is_control_account` | `1` for AR/AP/inventory/fixed-asset control accounts. These are written only by their subledger. |
   | `control_subledger` | `receivables`, `payables`, `inventory`, `fixed_assets`. |
   | `requires_cost_centre` | `1` where a posting must carry a cost centre. |
   | `is_reconcilable` | `1` for bank and clearing accounts. |

2. Replace `chart-of-accounts.csv`.

3. Re-run the seeder:

   ```bash
   php artisan db:seed --class=Database\\Seeders\\ChartOfAccountsSeeder
   ```

   It validates before it writes — duplicate codes, missing parents, invalid classes,
   a control account that also allows manual entry — and reports every problem rather
   than importing a broken chart.

4. Update `config/accounting.php` so the handful of accounts the engine resolves by
   code (retained earnings, current year earnings, suspense, rounding, customer
   deposits, GRNI) point at the official chart's codes. Nothing else in the codebase
   refers to an account code directly.

## Once postings exist

Re-running the seeder updates account attributes in place and adds new accounts. It
does not delete: an account carrying history cannot be removed (the database refuses
it), and codes are never reused. Retiring an account means setting `is_active` to 0.
