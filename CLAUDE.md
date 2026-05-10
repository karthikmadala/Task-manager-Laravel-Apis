# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Crypto Portfolio & Transaction Backend** — Laravel 12 REST API (JSON-only, no Blade views).  
All endpoints are under `/api/v1/`. Versioned controllers live in `app/Http/Controllers/Api/V1/`.

## Commands

```bash
# One-step initial setup
composer run setup

# Dev server (API + queue worker + log tail, runs concurrently)
composer run dev

# Run all tests
composer run test

# Single test file / method
php artisan test tests/Feature/Api/AuthTest.php
php artisan test --filter=test_method_name

# Code formatting (Laravel Pint)
./vendor/bin/pint

# Database operations
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed --class=UserSeeder
php artisan db:seed --class=TokenSeeder

# Queue worker (Redis)
php artisan queue:work redis --tries=3 --backoff=10

# Generate Postman collection
php artisan export:postman
```

## Architecture

### Request / Response Flow

Every controller returns `api_response()` — global helper in [app/Helpers/helpers.php](app/Helpers/helpers.php).  
Wraps all responses in `{ success, message, data, errors }`.  
**Never** call `response()->json()` directly.

### Authentication

Laravel Sanctum with token expiry (`SANCTUM_EXPIRATION` minutes).  
Middleware stack on authenticated routes:
- `check.token.expiry` — rejects expired tokens  
- `store.api.session` — writes activity to `sessions` table  
- `log.api.request` — async-safe write to `api_logs` table  
- `role:admin` — RBAC guard (`User::role` is `user` | `admin`)

MetaMask login: nonce-based EIP-191 signature verification. Nonce stored on `wallets.metamask_nonce`, rotated after each successful verify. Signature recovery requires **PHP `ext-gmp`** extension.

### Service / Repository Pattern

Controllers → FormRequest validation → Service → Repository → Model.  
Thin controllers, business logic in Services, DB abstraction in Repositories.  
Interfaces live in `app/Repositories/Contracts/`, implementations in `app/Repositories/Eloquent/`.  
Bind in `AppServiceProvider::register()`. Policies registered via `Gate::policy()` in `AppServiceProvider::boot()`.

### Modules (currently implemented)

| Module | Controller | Service | Repository |
|---|---|---|---|
| Auth | AuthController | AuthService | — |
| Profile | ProfileController | — | — |
| Wallet | WalletController | WalletService | WalletRepository |
| Portfolio | PortfolioController | PortfolioService | — |
| Chain Info | ChainInfoController | PortfolioService | — |

`Transaction` module (TransactionController / TransactionService / TransactionRepository) is **planned but not yet implemented**.

### Authorization

All wallet mutations call `$this->authorize()` — ownership enforced in `WalletPolicy`.  
All queries must be scoped to `auth()->id()` — BOLA prevented at policy level.

### Crypto Services (`app/Services/Crypto/`)

- `CoinGeckoService` — token price fetching (cached in Redis, TTL 300 s from `config/crypto.php`)  
- `AlchemyService` — on-chain EVM balance fetching (ETH + Polygon)  
- `EvmRpcService` — direct JSON-RPC fallback for all EVM chains; also handles `toDecimalUnits()` conversions  
- `ExplorerService` — Etherscan-compatible APIs for native + ERC-20 balances (ETH, BNB, Polygon)  
- `BlockCypherService` — Bitcoin read-only balances (satoshi-denominated)  
- `EthereumSignatureService` — EIP-191 MetaMask signature recovery using `simplito/elliptic-php` + `kornrunner/keccak`  

**Balance fetch priority order** (native and ERC-20):  
1. Alchemy (ETH/Polygon only, requires `ALCHEMY_API_KEY`)  
2. Explorer API (all EVM chains, requires chain-specific API key)  
3. Direct JSON-RPC fallback (always available)

All HTTP calls use Guzzle with timeouts. Failures throw domain exceptions caught by `Handler.php`.

> **Note:** `BlockchainNodeService` (Node.js microservice for tx broadcast) and queue jobs (`UpdateWalletBalancesJob`, `FetchTokenPricesJob`) are **planned but not yet implemented**.

### Portfolio Caching

- `portfolio:user:{id}` — 1-minute TTL, invalidated on wallet sync  
- `portfolio:wallet:{id}` — 1-minute TTL, invalidated on wallet sync  
- Supports `?refresh=true` on portfolio endpoints to force live RPC/explorer fetch before reading cache

### Enums (`app/Enums/`)

- `ChainType` — `eth | bnb | polygon | btc` — has `isEvm()`, `isReadOnly()`, `nativeSymbol()`, `addressPattern()` helpers  
- `WalletType` — `external | metamask`  
- `TransactionStatus` — `pending | submitted | confirmed | failed`  

Use enum values for all DB columns — never raw strings in service/controller code.

### Config

`config/crypto.php` — blockchain API keys, RPC URLs, explorer endpoints, chain metadata, sync intervals.  
Local env uses testnet URLs automatically when `APP_ENV=local`.

### Database

MySQL with UUID primary keys on all crypto tables (`HasUuids` trait).  
Foreign keys enforced. Indexed on query-hot columns (wallet_id, user_id+created_at, tx_hash).  
All balance math uses `bcmath` functions (`bcadd`, `bcmul`, `bccomp`) for precision arithmetic.

Seeded test accounts: `admin@example.com` / `user@example.com` (password: `password`).

### Rate Limiting (defined in AppServiceProvider)

- `auth` — 10 req/min per email/address/IP (login, register, MetaMask nonce/verify)  
- `api` — 120 req/min per user ID or IP  
- `broadcast` — 10 req/min (reserved for transaction broadcast endpoint)

### Testing

Tests use SQLite in-memory (`phpunit.xml`). Feature tests in `tests/Feature/Api/`.  
Postman collection: `tests/Feature/postman_collection.json`.

## Security Rules

- Never store private keys anywhere in this codebase  
- All wallet addresses validated against per-chain regex in FormRequest before DB write (`ChainType::addressPattern()`)  
- Sensitive fields encrypted with `encrypt()` / `decrypt()` (AES-256-CBC via APP_KEY)  
- `metamask_nonce` is in `$hidden` on `Wallet`; use `getRawOriginal('metamask_nonce')` to access it in service code  
- Internal Node service calls must include `X-Service-Secret` header (HMAC-SHA256 shared secret)
