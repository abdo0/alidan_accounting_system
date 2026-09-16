# Al-Idan Accounting System — Design & Implementation Documentation

**Version:** Draft 1 · 2026-09-16
**Repository:** `alidan_accounting_system` (Laravel 13.32 · PHP 8.4 · Filament 5.8 · PostgreSQL 16.10)
**Status:** Design proposal. Not approved. Assumptions unconfirmed — see [01 §1.6](01-source-analysis.md).

---

## Read this first

The two source PDFs supplied with the brief (`Financial Accounting.pdf`,
`THE_ACCOUNTING_SYSTEM.pdf`) were described as documenting current requirements
and implementation. **They do not.** Both are academic teaching material on
financial-accounting fundamentals — a 132-slide lecture deck and a 5-page tutor
handout that ends in an exam question.

They are still valuable, and this design uses them heavily: together they give a
complete and internally consistent statement of the accounting *semantics* the
system must implement, and Doc B's processing pipeline maps almost directly onto
the module architecture. But they say nothing about Al-Idan's actual operations —
no entities, currencies, volumes, users, statutory regime, existing software, or
data to migrate.

Everything operational in these documents is therefore an **explicit assumption**,
recorded in [01 §1.6](01-source-analysis.md) with a named way to confirm it. Six
of those assumptions are blocking and should be closed in a single workshop before
Phase 1 design is frozen ([08 §8.8](08-implementation-plan.md)).

---

## Contents

| Doc | Covers |
|---|---|
| [01 — Source Analysis](01-source-analysis.md) | What the PDFs contain, 40 derived functional requirements with traceability, 14 gaps, 15-row assumption register |
| [02 — Architecture](02-architecture.md) | Module map, the single-writer GL rule, multi-entity model, deployment, scalability, security |
| [03 — Data Model](03-data-model.md) | Chart of accounts and code scheme, dimensions, ERD, production DDL, mandatory fields, audit trail |
| [04 — Transactions & Workflows](04-transactions-workflows.md) | JE lifecycle, 18 posting validations, transaction flows with journal mappings, approvals, period close, intercompany, multicurrency |
| [05 — Cost Centres](05-cost-centres.md) | **The feasibility assessment.** Four approaches evaluated, full schema changes, posting logic, allocation methods, performance, migration, effort estimate |
| [06 — Integrations & APIs](06-integrations-api.md) | Integration inventory, staging pattern, bank import, REST API, error handling |
| [07 — Reporting & Controls](07-reporting-controls.md) | Report catalogue, cash-flow mapping, 15-ratio KPI pack, preventive/detective controls, reconciliations, auditability |
| [08 — Implementation Plan](08-implementation-plan.md) | MVP scope, 10-phase roadmap, migration steps, testing, training, risks, acceptance criteria |

---

## The headline answers

**Should Al-Idan add cost centres?** Yes — and the column should be added in
Phase 1 even though the feature ships in Phase 4. Adding `cost_centre_id` to
`journal_lines` now costs about one day; retrofitting it after two years of
postings costs roughly 46 extra developer-days and leaves permanently inferior
historical data. Full argument and numbers: [05 §5.1](05-cost-centres.md) and
[§5.11](05-cost-centres.md).

**How?** As a dimension column on the posting line, not as account-code
segmentation, not as free tagging, and not as a parallel analytic ledger — all
three alternatives evaluated and scored in [05 §5.2](05-cost-centres.md).
Cost-centre reporting stays fast because `gl_balances` is keyed by cost centre
from day one ([03 §3.6](03-data-model.md)).

**What does it cost?** Cost centres with reporting: **6–9 weeks**. Adding budgets
and the allocation engine: **a further 5–8 weeks**. Retrofitted instead of built
in: **~1.65×**, rising toward 2.5× with more history.

**What should be built first?** A GL that a qualified accountant will sign a trial
balance from. Phases 0–4a (foundations, GL, AR/AP, cash, cost centres) is
**6–9 months** and is the scope I would recommend committing to before deciding on
inventory, payroll, tax and consolidation.

**What is the biggest risk?** Not technical. It is that the real requirements are
still unknown because the supplied documents are coursework (R-01,
[08 §8.6](08-implementation-plan.md)). One workshop closes most of it.

---

## Design decisions worth knowing before reading further

1. **The GL is the only writer of financial truth.** Subledgers produce journal
   entry drafts; they never keep their own balances. ([02 §2.1](02-architecture.md))
2. **Posted entries are immutable** — no edit, no delete, enforced in the
   database. Corrections are reversals. ([02 §2.6](02-architecture.md))
3. **Accrual-native.** Cash basis is a report-time transformation, never a second
   posting mode. ([01 §1.3](01-source-analysis.md))
4. **Natural accounts only in the account code**; department, branch, project and
   cost centre are dimensions beside it. ([03 §3.1](03-data-model.md))
5. **Balances are materialised at post**, never summed from the ledger for routine
   reporting. ([02 §2.5](02-architecture.md))
6. **Cost centres apply to the P&L, not the balance sheet** — following Doc A's own
   statement that the balance sheet is prepared for the operation as a whole.
   ([05 §5.5](05-cost-centres.md))
7. **Multi-entity from day one in the schema**, even if only one entity is live.
   Retrofitting `entity_id` is as expensive as retrofitting a dimension.
   ([02 §2.3](02-architecture.md))

---

## Caveats

- **Nothing here has been implemented.** These are design documents; the
  repository currently contains a fresh Laravel + Filament install. The core
  ledger DDL *has* been executed against PostgreSQL 16 to confirm it is valid and
  that its constraints reject bad data — see the verification note in
  [03](03-data-model.md) — but no application code exists yet.
- **Iraqi statutory and tax content is unverified.** Assumption A-04 flags this
  explicitly. Confirm the applicable regime, the Unified Accounting System code
  list, and all tax rates with a local practitioner before building the tax module.
- **Effort estimates assume the team described in [08](08-implementation-plan.md).**
  They are ranges, not commitments, and they exclude the finance team's own time,
  which is substantial and routinely underestimated.
- Spelling: "cost centre" (British) is used consistently, including in identifiers.
  Changing to "cost center" is trivial now and annoying after Phase 1.
