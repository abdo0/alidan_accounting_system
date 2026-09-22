# 06 — Integrations & APIs

**Reads from:** [02](02-architecture.md), [04](04-transactions-workflows.md)
**Gap addressed:** G-06

---

## 6.1 Principle

Every integration is a **producer of documents or journal entry drafts**, never a
direct writer of `journal_lines`. An inbound bank statement does not post; it
creates candidate transactions a human or a matching rule turns into entries. This
keeps the posting invariants ([04 §4.2](04-transactions-workflows.md)) in one place
and means a broken integration produces a queue of unprocessed items rather than a
corrupt ledger.

Three consequences:
- All inbound data lands in a **staging table** first, with its raw payload retained.
- Nothing posts without passing the same validation as manual entry.
- Every integration is **idempotent by external key** — replaying yesterday's file
  must not double-post.

---

## 6.2 Integration inventory

| # | System | Direction | Mode | Format | Priority |
|---|---|---|---|---|---|
| I-1 | Bank statements | In | Batch, daily | MT940 / CAMT.053 / CSV | Phase 3 |
| I-2 | Payment instruction to bank | Out | Batch | Bank-specific CSV/XML | Phase 3 |
| I-3 | Payroll system | In | Batch, monthly | CSV/JSON summary | Phase 7 |
| I-4 | HR / employee master | In | Batch nightly | CSV/API | Phase 7 |
| I-5 | POS / sales system | In | Batch daily or near-real-time | JSON | Phase 2 |
| I-6 | Procurement / PO system | In | Real-time | REST | Phase 2 |
| I-7 | Existing COSQC application | In | Batch | Postgres FDW / API | Phase 2 |
| I-8 | Tax authority e-filing | Out | Batch, periodic | Per authority | Phase 7 |
| I-9 | Document storage (MinIO/S3) | Both | Real-time | S3 API | Phase 1 |
| I-10 | Google Drive (existing use) | Out | Batch | Drive API | Phase 5 |
| I-11 | FX rates (CBI or provider) | In | Daily | JSON | Phase 8 |
| I-12 | BI / reporting tools | Out | Pull | Read replica + views | Phase 3 |
| I-13 | Email/SMS notification | Out | Real-time | SMTP/provider | Phase 1 |

Al-Idan already operates MinIO with an established backup chain and a Postgres
estate with a working restored replica — I-9 and I-7 should reuse that
infrastructure rather than introduce new dependencies.

---

## 6.3 Real-time vs batch

| Use real-time (API/webhook) when | Use batch when |
|---|---|
| A user is waiting for the result | Data arrives on a natural cycle (daily statement, monthly payroll) |
| The source system needs an immediate answer (credit check, PO approval) | Volume is high and latency is irrelevant |
| Events are low-volume and discrete | The source can only export files |
| | The data needs human review before posting — **which is most financial inbound data** |

Default to **batch with human review** for anything that posts. Real-time posting
from an external system is appropriate only where the mapping is unambiguous and
the source is trusted — and even then, run it through the same staging table so
there is something to inspect when it goes wrong.

---

## 6.4 Inbound pattern

```
external source
      │
      ▼
┌──────────────┐   raw payload retained, external_id unique
│   staging    │   integration_messages (id, source, external_id, payload jsonb,
│              │     received_at, status, error, attempts)
└──────┬───────┘
       │ transform + validate
       ▼
┌──────────────┐   business objects: bank_statement_lines, pos_sales,
│   candidate  │   payroll_summaries, …
└──────┬───────┘
       │ match / enrich (rules + human review)
       ▼
┌──────────────┐
│ JournalEntry │  → posting engine → ledger
│    Draft     │
└──────────────┘
```

```sql
CREATE TABLE integration_messages (
    id bigserial PRIMARY KEY,
    source varchar(40) NOT NULL,              -- 'bank:rafidain' | 'pos' | 'payroll'
    direction char(1) NOT NULL CHECK (direction IN ('I','O')),
    external_id varchar(160) NOT NULL,        -- idempotency key
    payload jsonb NOT NULL,                   -- raw, unmodified
    payload_hash char(64) NOT NULL,
    status varchar(20) NOT NULL DEFAULT 'received',
        -- received | transformed | matched | posted | failed | ignored
    attempts smallint NOT NULL DEFAULT 0,
    last_error text,
    journal_entry_id bigint REFERENCES journal_entries(id),
    received_at timestamptz NOT NULL DEFAULT now(),
    processed_at timestamptz,
    UNIQUE (source, external_id)              -- replay safety
);
```

The `UNIQUE (source, external_id)` constraint is the whole idempotency story: a
re-sent file, a retried webhook, or a double-click on "import" all collide and are
skipped rather than duplicated.

---

## 6.5 Bank statement import (I-1) — worked detail

The highest-value integration, and the one that makes bank reconciliation feasible.

1. **Ingest** MT940/CAMT.053/CSV. Each statement line gets
   `external_id = hash(account, date, amount, bank_reference, sequence)`.
2. **Auto-match** in order of confidence:
   - Exact match on a payment/receipt reference already recorded → match.
   - Amount + date window (±3 days) + partner name fuzzy match → suggest.
   - Recurring rule (e.g. "description contains ZAIN → 6520 Telephone,
     CC310") → propose a draft entry.
   - No match → manual queue.
3. **Human confirms** anything not exactly matched.
4. **Post** confirmed items; unmatched bank lines remain visible as reconciling
   items on the bank reconciliation ([07 §7.5](07-reporting-controls.md)).

Target: 80%+ auto-match after three months of rule learning. Below ~60%, the
integration costs more time than it saves and the rules need attention.

---

## 6.6 Outbound API

A REST API under `/api/v1`, Sanctum or OAuth2 token auth, scoped per integration.

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/accounts` | Chart of accounts |
| GET | `/cost-centres` | Cost centre tree |
| GET | `/journal-entries?from=&to=&status=` | Ledger export |
| POST | `/journal-entries` | Submit a draft (never posts directly) |
| GET | `/trial-balance?period=&cost_centre=` | TB, optionally by dimension |
| GET | `/balances?account=&period=&cost_centre=` | Balance query |
| POST | `/sales-invoices` | Create invoice from an external system |
| POST | `/vendor-bills` | Create bill |
| GET | `/customers/{id}/statement` | Statement of account (Doc B doc type #10) |
| GET | `/reports/{code}?params` | Run a defined report, JSON or PDF |
| POST | `/integration/{source}/messages` | Generic inbound webhook |

Conventions:
- **Idempotency-Key header mandatory on all POSTs**; a repeat returns the original
  result rather than creating a second document.
- Cursor pagination (`?cursor=`), never offset — offset pagination over a growing
  ledger silently skips rows.
- Monetary amounts as **strings** in minor units with an explicit currency, never
  floats. `{"amount": "13100000", "currency": "IQD"}`.
- Dates ISO-8601; all timestamps UTC with offset.
- Responses carry `X-Request-Id`, which is also written to `audit_log.request_id`.

---

## 6.7 Error handling

| Failure | Response |
|---|---|
| Malformed payload | `422`, message stored, `status = failed`, no retry (retrying invalid data never helps) |
| Transient (network, timeout) | Exponential backoff 1m/5m/15m/1h/6h, max 5 attempts, then `failed` + alert |
| Business rule violation (period closed, account inactive) | `status = failed` with the specific rule; item goes to a human queue, **never auto-corrected** |
| Duplicate external_id | `200` with the original result; logged as duplicate, not an error |
| Partial batch failure | Per-item status. **Never roll back the whole batch** — one bad row must not block 500 good ones |
| Downstream unavailable (outbound) | Queue, retry, alert after threshold; the ledger is unaffected |

Operational requirements: a **dead-letter queue UI** where finance can see stuck
items with their raw payload, a daily digest of failures to the controller, and
alerting when any queue depth exceeds a threshold or an item ages past 24 hours.
Silent integration failure is how a month-end gets a nasty surprise.

---

## 6.8 Security of integrations

- Per-integration credentials with least privilege; no shared API key.
- IP allow-listing for bank and tax endpoints.
- Webhook signature verification (HMAC) on all inbound.
- Mutual TLS for bank connections where supported.
- Payloads containing PII or bank details encrypted at rest in `integration_messages`.
- Every integration action attributed to a service user and fully audited
  ([03 §3.12](03-data-model.md)); service accounts can never approve.
