# SHH-01 specification

These three documents are the contract for the Shams Al-Haylan Financial & Accounting System.

| Document | File | Audience |
|---|---|---|
| A — Business, accounting and functional requirements (Arabic) | `SHH-01_Document_A_Business_Accounting_Functional_Requirements.docx` | Management, accounting, sign-off |
| B — Technical and developer specification | `SHH-01_Document_B_Technical_Developer_Specification.md` | Development |
| C — Data dictionary and accounting mapping | `SHH-01_Document_C_Data_Dictionary_and_Accounting_Mapping.xlsx` | Development and accounting |

## Source priority

Where Document B names a rule (`VR-nn`), a posting rule (`PR-nn`), a transaction type (`TT-nn`), a report (`RPT-nn`), a migration control (`MC-nn`) or a test (`UAT-nnn`), the authoritative definition is the tab of the same name in Document C.

## How the system reads Document C

The application never reads the workbook at runtime. `php artisan shh:spec:extract` writes one CSV per tab to `database/data/shh/`, together with `manifest.json`, which records the workbook's SHA-256. The seeders load reference data from those CSVs. When Document C is re-issued, run the extractor again and review the diff of `database/data/shh/` before seeding.

A few files in `database/data/shh/` are not tabs of Document C. Each one names its source:

| File | Source |
|---|---|
| `account_groups.csv` | Document A, Appendix A — the 15 group codes that the chart's parent column refers to |
| `closing_tasks.csv` | Document A §13 — the 25-task monthly closing checklist (English wording is a translation for review) |
| `chain_step_pairs.csv` | Document B §2.6 — the permitted account pair for each funding-chain step |
| `derived_posting_rules.csv` | Document C tab 12 — posting rules PR-36…43 for transaction types that tab 13 does not cover. Loaded as **Pending** until the Finance Manager approves them |
| `selector_aliases.csv` | Document C tabs 12–13 — the prose account selectors ("final economic account", "correct account") as named aliases |
| `file1_layout.csv` | Document C tab 11 — the column layout of the authoritative journal workbook, used by the migration importer |

## Not in these documents

Document C carries the control totals of the authoritative ledger, but not the ledger itself. The 1,195 historical journal entries, the duplicate flags, the reclassification log and the detailed exception rows are in the authoritative workbook `قيود شمس الهيلان - مصحح تمويل الراشدية.xlsx`. The system imports them with `php artisan shh:migrate:file1` once that workbook is supplied, and the thirty migration controls (MC-01…MC-30) prove the import.
