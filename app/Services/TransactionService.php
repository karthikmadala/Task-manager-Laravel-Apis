<?php

namespace App\Services;

use App\DTOs\GasEstimate;
use App\DTOs\TransactionDto;
use App\Enums\ChainType;
use App\Enums\TransactionStatus;
use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidTransactionException;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionService
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly GasEstimationService $gasEstimationService,
        private readonly TransactionBroadcastService $broadcastService,
        private readonly TransactionMonitorService $monitorService,
        private readonly PortfolioService $portfolioService,
    ) {}

    public function prepareTransaction(array $data): array
    {
        $this->validateTransactionData($data);

        $dto = TransactionDto::fromArray($data);
        $wallet = $this->getWalletForTransaction($dto);

        $this->validateWalletOwnership($wallet, auth()->user());
        $this->validateSufficientBalance($wallet, $dto);

        $gasEstimate = $this->gasEstimationService->estimateGas($dto);
        $txParams    = $this->gasEstimationService->buildMetaMaskTxParams($dto, $gasEstimate);

        return [
            'transaction' => $dto->toArray(),
            'gas_estimate' => $gasEstimate->toArray(),
            'tx_params'   => $txParams,
            'wallet' => [
                'address' => $wallet->address,
                'chain_type' => $wallet->chain_type->value,
                'chain_label' => $wallet->chain_type->label(),
                'balance' => $this->getWalletBalance($wallet, $dto->chain),
            ],
        ];
    }

    public function createTransaction(array $data): Transaction
    {
        $this->validateTransactionData($data);

        return DB::transaction(function () use ($data) {
            $dto = TransactionDto::fromArray($data);
            $wallet = $this->getWalletForTransaction($dto);

            $this->validateWalletOwnership($wallet, auth()->user());
            $this->validateSufficientBalance($wallet, $dto);

            $gasEstimate = $this->gasEstimationService->estimateGas($dto);

            $transactionData = [
                'wallet_id' => $wallet->id,
                'user_id' => auth()->id(),
                'token_id' => $this->getTokenId($dto->tokenAddress),
                'from_address' => $dto->fromAddress,
                'to_address' => $dto->toAddress,
                'amount' => $dto->amount,
                'chain_type' => $dto->chain->value,
                'status' => TransactionStatus::PENDING->value,
                'gas_limit' => $gasEstimate->gasLimit,
                'gas_price_gwei' => $gasEstimate->gasPrice,
                'max_fee_per_gas' => $gasEstimate->maxFeePerGas,
                'max_priority_fee_per_gas' => $gasEstimate->maxPriorityFeePerGas,
                'contract_address' => $dto->contractAddress,
                'method_signature' => $dto->method,
                'method_params' => $dto->methodParams,
                'signing_method' => 'client', // Default to client signing
            ];

            $transaction = $this->transactionRepository->create($transactionData);

            Log::info('Transaction created', [
                'transaction_id' => $transaction->id,
                'user_id' => auth()->id(),
                'chain' => $dto->chain->value,
                'amount' => $dto->amount,
            ]);

            return $transaction;
        });
    }

    public function broadcastTransaction(Transaction $transaction, ?string $signature = null): Transaction
    {
        $this->validateTransactionOwnership($transaction, auth()->user());

        if ($transaction->status !== TransactionStatus::PENDING) {
            throw new InvalidTransactionException('Transaction must be in PENDING status to broadcast');
        }

        try {
            if ($signature) {
                // Client-signed transaction
                $transaction = $this->broadcastService->broadcastClientSigned($transaction, $signature);
            } else {
                // Backend-signed transaction
                $transaction = $this->broadcastService->broadcastBackendSigned($transaction);
            }

            // Start monitoring
            $this->monitorService->monitorTransaction($transaction);

            return $transaction;
        } catch (\Exception $e) {
            Log::error('Failed to broadcast transaction', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function updateTransactionStatus(Transaction $transaction, TransactionStatus $status): Transaction
    {
        $updatedTransaction = $this->monitorService->updateTransactionStatus($transaction, $status);

        // If transaction is confirmed, update portfolio
        if ($status === TransactionStatus::CONFIRMED) {
            $this->portfolioService->syncWallet($transaction->wallet);
        }

        return $updatedTransaction;
    }

    public function getUserTransactions(User $user, array $filters = []): LengthAwarePaginator
    {
        return $this->transactionRepository->getByUserWithFilters($user, $filters);
    }

    public function getTransactionById(string $id): ?Transaction
    {
        return $this->transactionRepository->getById($id);
    }

    public function checkTransactionStatus(Transaction $transaction): Transaction
    {
        $this->validateTransactionOwnership($transaction, auth()->user());

        return $this->monitorService->monitorTransaction($transaction);
    }

    public function cancelTransaction(Transaction $transaction): Transaction
    {
        $this->validateTransactionOwnership($transaction, auth()->user());

        if ($transaction->status !== TransactionStatus::PENDING) {
            throw new InvalidTransactionException('Only pending transactions can be cancelled');
        }

        $transaction->update([
            'status' => TransactionStatus::FAILED->value,
            'error_message' => 'Transaction cancelled by user',
        ]);

        Log::info('Transaction cancelled', [
            'transaction_id' => $transaction->id,
            'user_id' => auth()->id(),
        ]);

        return $transaction->fresh();
    }

    private function validateTransactionData(array $data): void
    {
        $required = ['from_address', 'to_address', 'chain_type', 'amount'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidTransactionException("Missing required field: {$field}");
            }
        }

        // Validate chain type
        if (!ChainType::tryFrom($data['chain_type'])) {
            throw new InvalidTransactionException('Invalid chain type');
        }

        // Validate addresses
        $chain = ChainType::from($data['chain_type']);
        $pattern = $chain->addressPattern();

        if (!preg_match($pattern, $data['from_address'])) {
            throw new InvalidTransactionException('Invalid from address format');
        }

        if (!preg_match($pattern, $data['to_address'])) {
            throw new InvalidTransactionException('Invalid to address format');
        }

        // Validate amount
        if (bccomp($data['amount'], '0', 18) <= 0) {
            throw new InvalidTransactionException('Amount must be greater than 0');
        }
    }

    private function getWalletForTransaction(TransactionDto $dto): Wallet
    {
        $wallet = Wallet::where('address', strtolower($dto->fromAddress))
            ->where('chain_type', $dto->chain->value)
            ->where('user_id', auth()->id())
            ->where('is_active', true)
            ->first();

        if (! $wallet && $dto->chain->isEvm()) {
            $wallet = Wallet::where('address', strtolower($dto->fromAddress))
                ->where('user_id', auth()->id())
                ->whereIn('chain_type', [ChainType::ETH->value, ChainType::BNB->value, ChainType::POLYGON->value])
                ->where('is_active', true)
                ->first();
        }

        if (! $wallet) {
            throw new InvalidTransactionException('Wallet not found or inactive');
        }

        return $wallet;
    }

    private function validateWalletOwnership(Wallet $wallet, User $user): void
    {
        if ($wallet->user_id !== $user->id) {
            throw new InvalidTransactionException('You do not own this wallet');
        }
    }

    private function validateTransactionOwnership(Transaction $transaction, User $user): void
    {
        if ($transaction->user_id !== $user->id) {
            throw new InvalidTransactionException('You do not own this transaction');
        }
    }

    private function validateSufficientBalance(Wallet $wallet, TransactionDto $dto): void
    {
        if ($dto->isNativeTransfer()) {
            $balance = $this->getWalletBalance($wallet, $dto->chain);
            if (bccomp($balance, $dto->amount, 18) < 0) {
                throw new InsufficientBalanceException($dto->amount, $balance, $dto->chain->nativeSymbol());
            }
            return;
        }

        if (isset($dto->contractAddress) && $dto->contractAddress) {
            $token = \App\Models\Token::where('contract_address', strtolower($dto->contractAddress))->first();
            if ($token) {
                $balance  = $this->getTokenBalance($wallet, $dto->contractAddress, $dto->chain);
                $decimals = (int) $token->decimals;
                if (bccomp($balance, $dto->amount, $decimals) < 0) {
                    throw new InsufficientBalanceException($dto->amount, $balance, $token->symbol);
                }
            }
        }
    }

    private function getTokenBalance(Wallet $wallet, string $contractAddress, ChainType $chain): string
    {
        $balance = $wallet->balances()
            ->where('chain_type', $chain->value)
            ->whereHas('token', fn($q) => $q->where('contract_address', strtolower($contractAddress)))
            ->first();

        return $balance ? (string) $balance->balance : '0';
    }

    private function getWalletBalance(Wallet $wallet, ?ChainType $chain = null): string
    {
        $targetChain = $chain ?? $wallet->chain_type;
        $balance = $wallet->balances()
            ->when($chain !== null, fn ($query) => $query->where('chain_type', $targetChain->value))
            ->whereHas('token', fn ($q) => $q->where('symbol', $targetChain->nativeSymbol()))
            ->first();

        return $balance ? (string) $balance->balance : '0';
    }

    private function getTokenId(?string $tokenAddress): ?string
    {
        if (!$tokenAddress) {
            return null;
        }

        $token = \App\Models\Token::where('contract_address', $tokenAddress)->first();

        return $token?->id;
    }
}
