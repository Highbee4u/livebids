# LiveBids — Live Video Auction Platform: Comprehensive Engineering Implementation Plan

**Framework**: Laravel 13 (API Only)  
**PHP Version**: PHP 8.3+  
**Architecture**: Domain-Driven Modular Monolith  
**Database**: PostgreSQL (MySQL / SQLite for local/test)  
**Cache / Lock / Leaderboard**: Redis  
**Real-Time Gateway**: Laravel Reverb (WebSocket)  
**Payments**: Paystack (Top-ups, Virtual Accounts, Transfers/Payouts)  
**Video Architecture**: Driver-Agnostic Token Service (Self-Hosted LiveKit primary)  
**Identity & Verification**: `welfare/verification` (Prembly IdentityPass driver with swappable Smile ID)  
**Notifications**: `welfare/notifications` (Termii SMS for OTP, Firebase Cloud Messaging for Push)

---

## 1. Architectural Philosophy & Senior Engineering Standards

1. **Separation of Concerns & Clean Domain Layer**:
   - HTTP controllers are thin dispatchers handling request validation, delegating to Domain Services, and returning API Resources.
   - Domain business logic is encapsulated in `app/Domain/{Module}/Services`.
   - Critical operations (Money, Bidding, State Transitions) are strictly interface-backed (`Contracts/`).

2. **Ledger Invariants & Financial Safety**:
   - Double-entry bookkeeping: every monetary operation posts two balancing entries (credit & debit). Sum of all ledger entries must always strictly equal zero.
   - Money is strictly held in integer minor units (kobo). No floating-point math anywhere.
   - Idempotency enforced on every mutating financial and bidding endpoint via `Idempotency-Key` HTTP header.
   - Balance is derived from ledger entries; caches or fast-read columns are verified against the ledger.

3. **High-Concurrency Bidding Engine**:
   - Server-authoritative state.
   - Redis atomic locks / ordered queue per auction during the 2–3 minute live window to eliminate race conditions.
   - Database transactions (`DB::transaction`) wrap the bid insertion and ledger lock.
   - Real-time event broadcasting to public presence channels via Laravel Reverb.

4. **Trust, Security & Privacy**:
   - NIN is hashed (`sha256(nin + salt)`) for duplicate detection. Raw NIN numbers are never stored in plaintext.
   - NIN photo from provider base64 is safely decoded and persisted to secure private disk storage (`storage/app/private/kyc/photos/`), never embedded as bloated blobs in database rows.
   - KYC Gate middleware strictly blocks unverified accounts from wallet top-ups, placing bids, listing auctions, or requesting payouts.
   - Dual-approver requirements for manual ledger adjustments in admin console.

---

## 2. Directory & Module Structure

```
app/
├── Domain/
│   ├── Common/
│   │   ├── Enums/
│   │   └── Traits/
│   ├── Identity/
│   │   ├── Contracts/IdentityServiceInterface.php
│   │   ├── Services/IdentityService.php
│   │   ├── Services/OtpService.php
│   │   └── Models/ (User, Device, KycRecord, Strike, OtpCode)
│   ├── Ledger/
│   │   ├── Contracts/LedgerServiceInterface.php
│   │   ├── Services/LedgerService.php
│   │   ├── Invariants/LedgerInvariantChecker.php
│   │   └── Models/ (Wallet, LedgerEntry)
│   ├── Payment/
│   │   ├── Contracts/PaymentProviderInterface.php
│   │   ├── Services/PaystackService.php
│   │   ├── Services/PayoutService.php
│   │   └── Models/ (Topup, Withdrawal, BankAccount, ProviderEvent)
│   ├── Auction/
│   │   ├── Contracts/AuctionStateServiceInterface.php
│   │   ├── Contracts/BiddingServiceInterface.php
│   │   ├── Services/AuctionStateService.php
│   │   ├── Services/BiddingService.php
│   │   ├── Events/ (BidPlaced, AuctionSold, AuctionExtended, AuctionEnded)
│   │   └── Models/ (Auction, AuctionMedia, Bid, Category, ReviewQueueItem, AuctionEvent)
│   ├── Order/
│   │   ├── Contracts/OrderServiceInterface.php
│   │   ├── Services/OrderService.php
│   │   ├── Events/ (OrderMessageSent, HandoverConfirmed)
│   │   └── Models/ (Order, OrderMessage, HandoverCode, OrderEvent)
│   ├── Dispute/
│   │   ├── Services/DisputeService.php
│   │   └── Models/ (Dispute, DisputeEvidence, Penalty)
│   ├── Video/
│   │   ├── Contracts/VideoDriverInterface.php
│   │   ├── Services/VideoTokenService.php
│   │   ├── Drivers/ (LiveKitDriver, NullDriver)
│   │   └── Models/ (LiveSession)
│   ├── Notification/
│   │   ├── Services/AuctionNotificationService.php
│   │   └── Models/ (Reminder, DeviceToken, NotificationLog, NotificationPref)
│   ├── Moderation/
│   │   ├── Services/ModerationService.php
│   │   └── Models/ (Report, ShillBidFlag, ProhibitedItem)
│   ├── Reputation/
│   │   ├── Services/ReputationService.php
│   │   └── Models/ (Review, SellerTrustScore)
│   └── Audit/
│       ├── Services/AuditLogService.php
│       └── Models/ (AdminAuditLog, PlatformSetting)
├── Http/
│   ├── Controllers/
│   │   ├── Api/V1/ (Auth, Profile, Kyc, Wallet, Topup, Withdrawal, Bank, Auction, Bid, Order, Dispute, Feed, Reminder, Review, Report)
│   │   └── Api/V1/Admin/ (Auctions, Users, Disputes, Payouts, Ledger, Settings, Kyc, Moderation)
│   ├── Middleware/ (IdempotencyKeyMiddleware, KycGateMiddleware, FeatureFlagMiddleware)
│   ├── Requests/
│   └── Resources/
└── Jobs/ & Console/Commands/
```

---

## 3. Implementation Phasing Strategy

- **Phase A**: Foundations & Financial Engine (M0, M1, M2, M3)
- **Phase B**: Live Auction Core & Real-Time Engine (M4, M5, M6, M7, M8)
- **Phase C**: Settlement, Escrow, Handover, Disputes & Admin (M9, M10, M13)
- **Phase D**: Trust, Safety, Reputation & Verification (M11, M12, M15, Testing & Hardening)
