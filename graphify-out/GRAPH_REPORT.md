# Graph Report - app+database+config+routes  (2026-05-02)

## Corpus Check
- 105 files · ~20,907 words
- Verdict: corpus is large enough that graph structure adds value.

## Summary
- 534 nodes · 628 edges · 47 communities detected
- Extraction: 89% EXTRACTED · 11% INFERRED · 0% AMBIGUOUS · INFERRED: 72 edges (avg confidence: 0.8)
- Token cost: 0 input · 0 output

## Community Hubs (Navigation)
- [[_COMMUNITY_API Controllers & Response Layer|API Controllers & Response Layer]]
- [[_COMMUNITY_Transaction Service & DTOs|Transaction Service & DTOs]]
- [[_COMMUNITY_Portfolio & Balance Service|Portfolio & Balance Service]]
- [[_COMMUNITY_Auth Service & Wallet Model|Auth Service & Wallet Model]]
- [[_COMMUNITY_Transaction Repository & Monitor Job|Transaction Repository & Monitor Job]]
- [[_COMMUNITY_EVM RPC & Service Provider|EVM RPC & Service Provider]]
- [[_COMMUNITY_Gas Estimation Service|Gas Estimation Service]]
- [[_COMMUNITY_Explorer API Service|Explorer API Service]]
- [[_COMMUNITY_Wallet Repository & Sync Job|Wallet Repository & Sync Job]]
- [[_COMMUNITY_Admin & API Logging|Admin & API Logging]]
- [[_COMMUNITY_Wallet Service|Wallet Service]]
- [[_COMMUNITY_Transaction Monitor Service|Transaction Monitor Service]]
- [[_COMMUNITY_Transaction Authorization Policy|Transaction Authorization Policy]]
- [[_COMMUNITY_Transaction Broadcast Service|Transaction Broadcast Service]]
- [[_COMMUNITY_Alchemy Blockchain Service|Alchemy Blockchain Service]]
- [[_COMMUNITY_CoinGecko Price Service|CoinGecko Price Service]]
- [[_COMMUNITY_Ethereum Signature Service|Ethereum Signature Service]]
- [[_COMMUNITY_Gas Estimate DTO|Gas Estimate DTO]]
- [[_COMMUNITY_Login Form Request|Login Form Request]]
- [[_COMMUNITY_Broadcast Transaction Request|Broadcast Transaction Request]]
- [[_COMMUNITY_Create Transaction Request|Create Transaction Request]]
- [[_COMMUNITY_Prepare Transaction Request|Prepare Transaction Request]]
- [[_COMMUNITY_Sign Transaction Request|Sign Transaction Request]]
- [[_COMMUNITY_MetaMask Nonce Request|MetaMask Nonce Request]]
- [[_COMMUNITY_MetaMask Verify Request|MetaMask Verify Request]]
- [[_COMMUNITY_Update Wallet Balances Job|Update Wallet Balances Job]]
- [[_COMMUNITY_User Model|User Model]]
- [[_COMMUNITY_Wallet Authorization Policy|Wallet Authorization Policy]]
- [[_COMMUNITY_Register Form Request|Register Form Request]]
- [[_COMMUNITY_Update Profile Request|Update Profile Request]]
- [[_COMMUNITY_Store Wallet Request|Store Wallet Request]]
- [[_COMMUNITY_BlockCypher Bitcoin Service|BlockCypher Bitcoin Service]]
- [[_COMMUNITY_Gas Price Update Job|Gas Price Update Job]]
- [[_COMMUNITY_Webhook Event Job|Webhook Event Job]]
- [[_COMMUNITY_User Factory|User Factory]]
- [[_COMMUNITY_Contract Call Exception|Contract Call Exception]]
- [[_COMMUNITY_Gas Estimation Exception|Gas Estimation Exception]]
- [[_COMMUNITY_Insufficient Balance Exception|Insufficient Balance Exception]]
- [[_COMMUNITY_Invalid Transaction Exception|Invalid Transaction Exception]]
- [[_COMMUNITY_Nonce Conflict Exception|Nonce Conflict Exception]]
- [[_COMMUNITY_Broadcast Failed Exception|Broadcast Failed Exception]]
- [[_COMMUNITY_API Session Middleware|API Session Middleware]]
- [[_COMMUNITY_Portfolio API Resource|Portfolio API Resource]]
- [[_COMMUNITY_Wallet Balance Resource|Wallet Balance Resource]]
- [[_COMMUNITY_Wallet API Resource|Wallet API Resource]]
- [[_COMMUNITY_Database Seeder|Database Seeder]]
- [[_COMMUNITY_Base Controller|Base Controller]]

## God Nodes (most connected - your core abstractions)
1. `api_response()` - 38 edges
2. `PortfolioService` - 21 edges
3. `GasEstimationService` - 18 edges
4. `TransactionService` - 17 edges
5. `ExplorerService` - 17 edges
6. `EvmRpcService` - 16 edges
7. `TransactionController` - 14 edges
8. `Transaction` - 14 edges
9. `WalletService` - 11 edges
10. `TransactionDto` - 9 edges

## Surprising Connections (you probably didn't know these)
- `successResponse()` --calls--> `api_response()`  [INFERRED]
  app\Traits\ApiResponse.php → app\Helpers\helpers.php
- `errorResponse()` --calls--> `api_response()`  [INFERRED]
  app\Traits\ApiResponse.php → app\Helpers\helpers.php

## Communities

### Community 0 - "API Controllers & Response Layer"
Cohesion: 0.04
Nodes (13): Handler, api_response(), RoleMiddleware, TransactionResource, errorResponse(), successResponse(), AuthController, ChainInfoController (+5 more)

### Community 1 - "Transaction Service & DTOs"
Cohesion: 0.09
Nodes (3): GasParameters, TransactionDto, TransactionService

### Community 2 - "Portfolio & Balance Service"
Cohesion: 0.16
Nodes (2): WalletBalance, PortfolioService

### Community 3 - "Auth Service & Wallet Model"
Cohesion: 0.11
Nodes (4): WalletFactory, Wallet, UserSeeder, AuthService

### Community 4 - "Transaction Repository & Monitor Job"
Cohesion: 0.11
Nodes (3): TransactionRepository, MonitorTransactionJob, Transaction

### Community 5 - "EVM RPC & Service Provider"
Cohesion: 0.19
Nodes (2): EvmRpcService, AppServiceProvider

### Community 6 - "Gas Estimation Service"
Cohesion: 0.22
Nodes (1): GasEstimationService

### Community 7 - "Explorer API Service"
Cohesion: 0.2
Nodes (1): ExplorerService

### Community 8 - "Wallet Repository & Sync Job"
Cohesion: 0.17
Nodes (2): WalletRepository, SyncIncomingTransactionsJob

### Community 9 - "Admin & API Logging"
Cohesion: 0.14
Nodes (4): LogApiRequest, ApiLog, UserResource, AdminController

### Community 10 - "Wallet Service"
Cohesion: 0.21
Nodes (1): WalletService

### Community 11 - "Transaction Monitor Service"
Cohesion: 0.33
Nodes (1): TransactionMonitorService

### Community 12 - "Transaction Authorization Policy"
Cohesion: 0.22
Nodes (1): TransactionPolicy

### Community 14 - "Transaction Broadcast Service"
Cohesion: 0.36
Nodes (1): TransactionBroadcastService

### Community 16 - "Alchemy Blockchain Service"
Cohesion: 0.46
Nodes (1): AlchemyService

### Community 17 - "CoinGecko Price Service"
Cohesion: 0.43
Nodes (1): CoinGeckoService

### Community 18 - "Ethereum Signature Service"
Cohesion: 0.43
Nodes (1): EthereumSignatureService

### Community 19 - "Gas Estimate DTO"
Cohesion: 0.33
Nodes (1): GasEstimate

### Community 21 - "Login Form Request"
Cohesion: 0.33
Nodes (1): LoginRequest

### Community 22 - "Broadcast Transaction Request"
Cohesion: 0.33
Nodes (1): BroadcastTransactionRequest

### Community 23 - "Create Transaction Request"
Cohesion: 0.33
Nodes (1): CreateTransactionRequest

### Community 24 - "Prepare Transaction Request"
Cohesion: 0.33
Nodes (1): PrepareTransactionRequest

### Community 25 - "Sign Transaction Request"
Cohesion: 0.33
Nodes (1): SignTransactionRequest

### Community 26 - "MetaMask Nonce Request"
Cohesion: 0.33
Nodes (1): MetaMaskNonceRequest

### Community 27 - "MetaMask Verify Request"
Cohesion: 0.33
Nodes (1): MetaMaskVerifyRequest

### Community 28 - "Update Wallet Balances Job"
Cohesion: 0.33
Nodes (1): UpdateWalletBalancesJob

### Community 29 - "User Model"
Cohesion: 0.33
Nodes (1): User

### Community 30 - "Wallet Authorization Policy"
Cohesion: 0.33
Nodes (1): WalletPolicy

### Community 32 - "Register Form Request"
Cohesion: 0.4
Nodes (1): RegisterRequest

### Community 33 - "Update Profile Request"
Cohesion: 0.4
Nodes (1): UpdateProfileRequest

### Community 34 - "Store Wallet Request"
Cohesion: 0.4
Nodes (1): StoreWalletRequest

### Community 35 - "BlockCypher Bitcoin Service"
Cohesion: 0.4
Nodes (1): BlockCypherService

### Community 36 - "Gas Price Update Job"
Cohesion: 0.5
Nodes (1): GasPriceUpdateJob

### Community 37 - "Webhook Event Job"
Cohesion: 0.5
Nodes (1): ProcessWebhookEventJob

### Community 38 - "User Factory"
Cohesion: 0.5
Nodes (1): UserFactory

### Community 40 - "Contract Call Exception"
Cohesion: 0.67
Nodes (1): ContractCallFailedException

### Community 41 - "Gas Estimation Exception"
Cohesion: 0.67
Nodes (1): GasEstimationFailedException

### Community 42 - "Insufficient Balance Exception"
Cohesion: 0.67
Nodes (1): InsufficientBalanceException

### Community 43 - "Invalid Transaction Exception"
Cohesion: 0.67
Nodes (1): InvalidTransactionException

### Community 44 - "Nonce Conflict Exception"
Cohesion: 0.67
Nodes (1): NonceConflictException

### Community 45 - "Broadcast Failed Exception"
Cohesion: 0.67
Nodes (1): TransactionBroadcastFailedException

### Community 46 - "API Session Middleware"
Cohesion: 0.67
Nodes (1): StoreApiSessionMetadata

### Community 47 - "Portfolio API Resource"
Cohesion: 0.67
Nodes (1): PortfolioResource

### Community 48 - "Wallet Balance Resource"
Cohesion: 0.67
Nodes (1): WalletBalanceResource

### Community 49 - "Wallet API Resource"
Cohesion: 0.67
Nodes (1): WalletResource

### Community 61 - "Database Seeder"
Cohesion: 0.67
Nodes (1): DatabaseSeeder

### Community 63 - "Base Controller"
Cohesion: 1.0
Nodes (1): Controller

## Knowledge Gaps
- **1 isolated node(s):** `Controller`
  These have ≤1 connection - possible missing edges or undocumented components.
- **Thin community `Portfolio & Balance Service`** (26 nodes): `WalletBalance.php`, `PortfolioService.php`, `WalletBalance`, `.casts()`, `.token()`, `PortfolioService`, `.activeWallets()`, `.alchemySupports()`, `.buildWalletResponse()`, `.computeChainTotals()`, `.__construct()`, `.evmChains()`, `.fetchBtcRawBalances()`, `.fetchErc20Balances()`, `.fetchEvmRawBalances()`, `.fetchNativeBalance()`, `.formatUsd()`, `.getBalancesForAddress()`, `.getPortfolio()`, `.getWalletPortfolio()`, `.groupWalletResponsesByAddress()`, `.resolveTokenPriceUsd()`, `.resolveWalletBalancePriceUsd()`, `.syncWallet()`, `.syncWalletChain()`, `.walletMeta()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `EVM RPC & Service Provider`** (20 nodes): `AppServiceProvider.php`, `EvmRpcService.php`, `EvmRpcService`, `.batchRpc()`, `.buildRetryClient()`, `.call()`, `.configUrl()`, `.__construct()`, `.getErc20Balance()`, `.getErc20Balances()`, `.getErc20BalancesSequential()`, `.getNativeBalance()`, `.hexToDecimal()`, `.indexBatchBalances()`, `.padAddress()`, `.rpc()`, `.toDecimalUnits()`, `AppServiceProvider`, `.boot()`, `.register()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Gas Estimation Service`** (19 nodes): `GasEstimationService.php`, `GasEstimationService`, `.__construct()`, `.decimalAmountToHex()`, `.decimalToHex()`, `.encodeErc20Transfer()`, `.encodeMethodCall()`, `.estimateConfirmationTime()`, `.estimateContractCall()`, `.estimateCostInUsd()`, `.estimateCostInWei()`, `.estimateGas()`, `.estimateGasLimit()`, `.estimateProxyGas()`, `.fallbackGasLimit()`, `.getCurrentGasPrice()`, `.getDefaultGasPrice()`, `.resolveTokenDecimals()`, `.validateGasParameters()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Explorer API Service`** (19 nodes): `ExplorerService.php`, `ExplorerService`, `.callProxy()`, `.callStandard()`, `.chainConfig()`, `.__construct()`, `.estimateEvmGas()`, `.getAddressTokenHoldings()`, `.getNativeBalance()`, `.getNativeTokenPriceUsd()`, `.getSuggestedGasPriceGwei()`, `.getTokenBalance()`, `.getTokenBalances()`, `.getTransactionCount()`, `.getUsdPriceMapForTokens()`, `.hexToDecimal()`, `.nativePriceMapKey()`, `.request()`, `.show()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Wallet Repository & Sync Job`** (16 nodes): `SyncIncomingTransactionsJob.php`, `WalletRepository.php`, `WalletRepository`, `.allForUser()`, `.create()`, `.delete()`, `.findActiveMetaMaskWalletByAddress()`, `.findByAddress()`, `.findByAddressAndUser()`, `.findById()`, `.updateNonce()`, `SyncIncomingTransactionsJob`, `.__construct()`, `.handle()`, `.syncWalletTransactions()`, `.wallet()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Wallet Service`** (12 nodes): `WalletService.php`, `WalletService`, `.__construct()`, `.ensureNotDuplicate()`, `.generateLinkNonce()`, `.generateLoginNonce()`, `.importExternal()`, `.listForUser()`, `.remove()`, `.validateAddress()`, `.verifyAndLink()`, `.verifyLoginSignature()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Transaction Monitor Service`** (10 nodes): `TransactionMonitorService.php`, `TransactionMonitorService`, `.checkTransactionStatus()`, `.__construct()`, `.getTransactionReceipt()`, `.isTransactionFinal()`, `.monitorTransaction()`, `.processWebhookEvent()`, `.updateConfirmations()`, `.updateTransactionStatus()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Transaction Authorization Policy`** (9 nodes): `TransactionPolicy.php`, `TransactionPolicy`, `.create()`, `.delete()`, `.forceDelete()`, `.restore()`, `.update()`, `.view()`, `.viewAny()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Transaction Broadcast Service`** (9 nodes): `TransactionBroadcastService.php`, `TransactionBroadcastService`, `.broadcastBackendSigned()`, `.broadcastClientSigned()`, `.broadcastSignedTransaction()`, `.__construct()`, `.getNextNonce()`, `.handleNonceConflict()`, `.signTransaction()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Alchemy Blockchain Service`** (8 nodes): `AlchemyService.php`, `AlchemyService`, `.baseUrl()`, `.__construct()`, `.getNativeBalance()`, `.getTokenBalances()`, `.hexToDecimal()`, `.rpc()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `CoinGecko Price Service`** (7 nodes): `CoinGeckoService.php`, `CoinGeckoService`, `.__construct()`, `.fetchPrices()`, `.getPrice()`, `.getPrices()`, `.refreshPrices()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Ethereum Signature Service`** (7 nodes): `EthereumSignatureService.php`, `EthereumSignatureService`, `.generateNonce()`, `.hashPersonalMessage()`, `.parseSignature()`, `.recoverAddress()`, `.verifySignature()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Gas Estimate DTO`** (6 nodes): `GasEstimate.php`, `GasEstimate`, `.__construct()`, `.fromArray()`, `.isEip1559()`, `.toArray()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Login Form Request`** (6 nodes): `LoginRequest.php`, `LoginRequest`, `.authorize()`, `.messages()`, `.prepareForValidation()`, `.rules()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Broadcast Transaction Request`** (6 nodes): `BroadcastTransactionRequest.php`, `BroadcastTransactionRequest`, `.authorize()`, `.failedValidation()`, `.messages()`, `.rules()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Create Transaction Request`** (6 nodes): `CreateTransactionRequest.php`, `CreateTransactionRequest`, `.authorize()`, `.failedValidation()`, `.messages()`, `.rules()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Prepare Transaction Request`** (6 nodes): `PrepareTransactionRequest.php`, `PrepareTransactionRequest`, `.authorize()`, `.failedValidation()`, `.messages()`, `.rules()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Sign Transaction Request`** (6 nodes): `SignTransactionRequest.php`, `SignTransactionRequest`, `.authorize()`, `.failedValidation()`, `.messages()`, `.rules()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `MetaMask Nonce Request`** (6 nodes): `MetaMaskNonceRequest.php`, `MetaMaskNonceRequest`, `.authorize()`, `.messages()`, `.prepareForValidation()`, `.rules()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `MetaMask Verify Request`** (6 nodes): `MetaMaskVerifyRequest.php`, `MetaMaskVerifyRequest`, `.authorize()`, `.messages()`, `.prepareForValidation()`, `.rules()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Update Wallet Balances Job`** (6 nodes): `UpdateWalletBalancesJob.php`, `UpdateWalletBalancesJob`, `.__construct()`, `.failed()`, `.handle()`, `.uniqueId()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `User Model`** (6 nodes): `User.php`, `User`, `.casts()`, `.isAdmin()`, `.transactions()`, `.wallets()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Wallet Authorization Policy`** (6 nodes): `WalletPolicy.php`, `WalletPolicy`, `.create()`, `.delete()`, `.view()`, `.viewAny()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Register Form Request`** (5 nodes): `RegisterRequest.php`, `RegisterRequest`, `.authorize()`, `.prepareForValidation()`, `.rules()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Update Profile Request`** (5 nodes): `UpdateProfileRequest.php`, `UpdateProfileRequest`, `.authorize()`, `.prepareForValidation()`, `.rules()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Store Wallet Request`** (5 nodes): `StoreWalletRequest.php`, `StoreWalletRequest`, `.authorize()`, `.prepareForValidation()`, `.rules()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `BlockCypher Bitcoin Service`** (5 nodes): `BlockCypherService.php`, `BlockCypherService`, `.__construct()`, `.getBalance()`, `.satoshiToBtc()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Gas Price Update Job`** (4 nodes): `GasPriceUpdateJob.php`, `GasPriceUpdateJob`, `.__construct()`, `.handle()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Webhook Event Job`** (4 nodes): `ProcessWebhookEventJob.php`, `ProcessWebhookEventJob`, `.__construct()`, `.handle()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `User Factory`** (4 nodes): `UserFactory.php`, `UserFactory`, `.definition()`, `.unverified()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Contract Call Exception`** (3 nodes): `ContractCallFailedException.php`, `ContractCallFailedException`, `.__construct()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Gas Estimation Exception`** (3 nodes): `GasEstimationFailedException.php`, `GasEstimationFailedException`, `.__construct()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Insufficient Balance Exception`** (3 nodes): `InsufficientBalanceException.php`, `InsufficientBalanceException`, `.__construct()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Invalid Transaction Exception`** (3 nodes): `InvalidTransactionException.php`, `InvalidTransactionException`, `.__construct()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Nonce Conflict Exception`** (3 nodes): `NonceConflictException.php`, `NonceConflictException`, `.__construct()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Broadcast Failed Exception`** (3 nodes): `TransactionBroadcastFailedException.php`, `TransactionBroadcastFailedException`, `.__construct()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `API Session Middleware`** (3 nodes): `StoreApiSessionMetadata.php`, `StoreApiSessionMetadata`, `.handle()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Portfolio API Resource`** (3 nodes): `PortfolioResource.php`, `PortfolioResource`, `.toArray()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Wallet Balance Resource`** (3 nodes): `WalletBalanceResource.php`, `WalletBalanceResource`, `.toArray()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Wallet API Resource`** (3 nodes): `WalletResource.php`, `WalletResource`, `.toArray()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Database Seeder`** (3 nodes): `DatabaseSeeder.php`, `DatabaseSeeder`, `.run()`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.
- **Thin community `Base Controller`** (2 nodes): `Controller.php`, `Controller`
  Too small to be a meaningful cluster - may be noise or needs more connections extracted.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `api_response()` connect `API Controllers & Response Layer` to `Admin & API Logging`, `Transaction Repository & Monitor Job`, `Explorer API Service`?**
  _High betweenness centrality (0.067) - this node is a cross-community bridge._
- **Why does `Transaction` connect `Transaction Repository & Monitor Job` to `Transaction Monitor Service`?**
  _High betweenness centrality (0.026) - this node is a cross-community bridge._
- **Why does `WalletBalance` connect `Portfolio & Balance Service` to `Wallet Repository & Sync Job`?**
  _High betweenness centrality (0.016) - this node is a cross-community bridge._
- **Are the 37 inferred relationships involving `api_response()` (e.g. with `.register()` and `.users()`) actually correct?**
  _`api_response()` has 37 INFERRED edges - model-reasoned connections that need verification._
- **What connects `Controller` to the rest of the system?**
  _1 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `API Controllers & Response Layer` be split into smaller, more focused modules?**
  _Cohesion score 0.04 - nodes in this community are weakly interconnected._
- **Should `Transaction Service & DTOs` be split into smaller, more focused modules?**
  _Cohesion score 0.09 - nodes in this community are weakly interconnected._