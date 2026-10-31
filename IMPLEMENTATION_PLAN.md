# LiveBids — Live Auction Platform

**Version**: 0.1 (MVP)  
**Stack**: Laravel 13 · PHP 8.3 · PostgreSQL · Redis · Laravel Reverb (WebSocket) · Paystack · LiveKit (Self-Hosted)

---

## Architecture

### Pattern: Modular Monolith
Single Laravel application with strict domain boundaries. All shared services live in `app/Domain/` with clean interfaces, enabling future extraction without refactoring.

```
app/
├── Domain/
│   ├── Ledger/        — Double-entry ledger (M2). Source of financial truth.
│   ├── Identity/      — OTP, NIN+liveness verification (M1). Wraps welfare/verification.
│   ├── Auction/       — Listing, lifecycle, bidding, state machine (M4, M7, M8).
│   ├── Payout/        — Paystack payouts, state machine, retry (M3).
│   ├── Notification/  — Auction-domain push/SMS dispatch (M5). Wraps welfare/notifications.
│   └── Audit/         — Admin audit log, two-approver enforcement (M13).
├── Http/
│   ├── Controllers/Api/V1/         — Mobile / user-facing API
│   └── Controllers/Api/V1/Admin/  — Admin back-office API
```

### External Packages
| Package | Purpose |
|---|---|
| `welfare/verification` | NIN + liveness verification via IdentityPass (Prembly). Swappable to Smile ID. |
| `welfare/notifications` | SMS (Termii) + FCM push. Handles OTP delivery via `sendSmsNow()`. |
| `laravel/sanctum` | Stateless API token authentication. |
| `laravel/reverb` | Self-hosted WebSocket server for real-time bidding. |
| `spatie/laravel-permission` | Role-based access control (buyer, seller, admin roles). |

### Money Conventions
- All amounts stored as **integer minor units** (kobo). `50000` = ₦500.00.
- Every money-touching endpoint requires an `Idempotency-Key` header.
- Double-entry ledger — every movement is two entries (debit + credit). Balances are derived, never stored directly.
- Nightly reconciliation job checks invariants: sum of all entries = 0, available never negative.

### Real-Time Architecture
```
Mobile Client ←WebSocket→ Laravel Reverb ←Broadcasting→ Redis Pub/Sub ←→ BiddingService
                                                                         ↓
                                                                    PostgreSQL (source of truth)
```
Bid state is always server-authoritative. Clients receive `leaderboard.snapshot` on connect and `leaderboard.delta` on each bid.

---

## Module Map

| ID | Module | Phase | Status |
|---|---|---|---|
| M0 | Platform foundation | A | ⏳ Pending |
| M1 | Identity & accounts | A | ⏳ Pending |
| M2 | Wallet & ledger | A | ⏳ Pending |
| M3 | Payments integration | A | ⏳ Pending |
| M4 | Auction listing | B | ⏳ Pending |
| M5 | Discovery & notifications | D | ⏳ Pending |
| M6 | Live streaming | B | ⏳ Pending |
| M7 | Bidding engine | B | ⏳ Pending |
| M8 | Auction lifecycle | B | ⏳ Pending |
| M9 | Orders, escrow & handover | C | ⏳ Pending |
| M10 | Disputes & cancellations | C | ⏳ Pending |
| M11 | Trust, safety & moderation | D | ⏳ Pending |
| M12 | Reputation & reviews | D | ⏳ Pending |
| M13 | Admin back office | C | ⏳ Pending |
| M14 | Mobile & web clients | A–D | — (client team) |
| M15 | Analytics & reporting | D | ⏳ Pending |

---

## API Versioning

All endpoints are prefixed `/api/v1/`. Breaking changes increment to `/api/v2/`.

### Error Format (all endpoints)
```json
{
    "message": "Human-readable message.",
    "errors": { "field": ["validation error"] },
    "error_code": "MACHINE_READABLE_CODE"
}
```

### Money Endpoint Requirement
Every endpoint that touches money **must** include:
```
Idempotency-Key: <uuid>
```
The server returns `409 Conflict` if the key has already been processed successfully.

---

## Cross-Cutting Performance Targets

| Area | Target |
|---|---|
| Bid → leaderboard broadcast | p95 < 500ms |
| API response time | p95 < 300ms |
| Video join latency | ~3 seconds |
| Scale target | 500 concurrent viewers per room, 50 rooms |

---

## Key Decisions Made

| Decision | Choice | Rationale |
|---|---|---|
| Identity verification | `welfare/verification` → IdentityPass | Already built, driver-agnostic interface for Smile ID fallback |
| Notifications | `welfare/notifications` | Termii SMS for OTP, FCM for push, no coupling to User model |
| Video streaming | Self-hosted LiveKit | Zero SaaS fees, WebRTC SFU < 300ms latency, official Flutter/RN SDKs |
| WebSocket | Laravel Reverb | Native Laravel, no Pusher dependency, self-hosted |
| Payment | Paystack | Nigeria market fit, Transfer API for payouts, virtual accounts for top-up |
| Service extraction | Modular monolith (`app/Domain/`) | ACID transactions intact, zero network latency, extractable later |
| NIN photo | Stored to private disk/S3, path in `kyc_records` | Avoid DB bloat; admin views via signed URL; audit logged |
| Timer on expiry | Option A + C: 60s decision window + anti-snipe (×3, +15s each) | Spec recommendation |

