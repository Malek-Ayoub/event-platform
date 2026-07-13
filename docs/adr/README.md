# Architecture Decision Records (ADR)

This directory documents **final architectural decisions** for the Event Platform backend.

## Purpose

ADRs capture *why* the system is built the way it is — not step-by-step implementation notes. A developer joining the project should read these before changing core behavior.

## Index

| ADR | Title | Status |
|-----|-------|--------|
| [ADR-0001](./ADR-0001-backend-v1-freeze.md) | Backend v1 Freeze | **Draft** — complete before `v1.0-backend-freeze` tag |

## When to add an ADR

- A decision affects multiple domains or is hard to reverse.
- A new contributor would ask "why is it like this?"
- The Financial Domain or tenancy model changes (post-v1 only with explicit approval).

## When **not** to add an ADR

- Bug fixes, small API response tweaks, or frontend-only changes.
- Implementation details that belong in code comments or tests.

## Status values

| Status | Meaning |
|--------|---------|
| **Draft** | Outline exists; not yet approved for freeze |
| **Accepted** | Frozen as part of Backend v1 |
| **Superseded** | Replaced by a later ADR (link the successor) |

## Related documents

- `IMPLEMENTATION_ROADMAP.md` — §v1 official execution order
- `blueprint_v1_3.md` — locked domain blueprint (v1.0–v1.3)
