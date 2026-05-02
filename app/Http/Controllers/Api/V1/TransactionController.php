<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Transaction\BroadcastTransactionRequest;
use App\Http\Requests\Transaction\CreateTransactionRequest;
use App\Http\Requests\Transaction\PrepareTransactionRequest;
use App\Http\Requests\Transaction\SignTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function __construct(
        private readonly TransactionService $transactionService,
    ) {}

    /**
     * POST /api/v1/transactions/prepare
     * Prepare transaction with gas estimate
     */
    public function prepare(PrepareTransactionRequest $request): JsonResponse
    {
        try {
            $result = $this->transactionService->prepareTransaction($request->validated());

            return api_response(true, 'Transaction prepared successfully', $result);
        } catch (\Exception $e) {
            Log::error('Failed to prepare transaction', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return api_response(false, 'Failed to prepare transaction: ' . $e->getMessage(), [], null, 500);
        }
    }

    /**
     * POST /api/v1/transactions
     * Create and broadcast transaction
     */
    public function store(CreateTransactionRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $signature = $data['signature'] ?? null;

            // Create transaction
            $transaction = $this->transactionService->createTransaction($data);

            // Broadcast if signature provided
            if ($signature) {
                $transaction = $this->transactionService->broadcastTransaction($transaction, $signature);
            }

            return api_response(true, 'Transaction created successfully', [
                'transaction' => new TransactionResource($transaction),
            ], null, 201);
        } catch (\Exception $e) {
            Log::error('Failed to create transaction', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return api_response(false, 'Failed to create transaction: ' . $e->getMessage(), [], null, 500);
        }
    }

    /**
     * GET /api/v1/transactions
     * List user transactions
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = [
                'chain_type' => $request->query('chain'),
                'status' => $request->query('status'),
                'wallet_id' => $request->query('wallet_id'),
                'from_date' => $request->query('from_date'),
                'to_date' => $request->query('to_date'),
                'per_page' => $request->query('per_page', 15),
            ];

            $transactions = $this->transactionService->getUserTransactions(auth()->user(), $filters);

            return api_response(true, 'Transactions retrieved successfully', [
                'transactions' => TransactionResource::collection($transactions),
                'pagination' => [
                    'total' => $transactions->total(),
                    'per_page' => $transactions->perPage(),
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve transactions', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return api_response(false, 'Failed to retrieve transactions: ' . $e->getMessage(), [], null, 500);
        }
    }

    /**
     * GET /api/v1/transactions/{transaction}
     * Get transaction details
     */
    public function show(Transaction $transaction): JsonResponse
    {
        try {
            $this->authorize('view', $transaction);

            $transaction = $this->transactionService->getTransactionById($transaction->id);

            if (!$transaction) {
                return api_response(false, 'Transaction not found', [], null, 404);
            }

            return api_response(true, 'Transaction retrieved successfully', [
                'transaction' => new TransactionResource($transaction),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve transaction', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);

            return api_response(false, 'Failed to retrieve transaction: ' . $e->getMessage(), [], null, 500);
        }
    }

    /**
     * POST /api/v1/transactions/{transaction}/status
     * Force status check
     */
    public function checkStatus(Transaction $transaction): JsonResponse
    {
        try {
            $this->authorize('update', $transaction);

            $transaction = $this->transactionService->checkTransactionStatus($transaction);

            return api_response(true, 'Transaction status checked successfully', [
                'transaction' => new TransactionResource($transaction),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to check transaction status', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);

            return api_response(false, 'Failed to check transaction status: ' . $e->getMessage(), [], null, 500);
        }
    }

    /**
     * POST /api/v1/transactions/{transaction}/cancel
     * Cancel pending transaction
     */
    public function cancel(Transaction $transaction): JsonResponse
    {
        try {
            $this->authorize('update', $transaction);

            $transaction = $this->transactionService->cancelTransaction($transaction);

            return api_response(true, 'Transaction cancelled successfully', [
                'transaction' => new TransactionResource($transaction),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cancel transaction', [
                'error' => $e->getMessage(),
                'transaction_id' => $transaction->id,
            ]);

            return api_response(false, 'Failed to cancel transaction: ' . $e->getMessage(), [], null, 500);
        }
    }

    /**
     * POST /api/v1/transactions/sign
     * Backend signing (for managed wallets)
     */
    public function sign(SignTransactionRequest $request): JsonResponse
    {
        try {
            $transaction = Transaction::findOrFail($request->validated('transaction_id'));

            $this->authorize('update', $transaction);

            // Note: Backend signing requires proper private key management
            // This is a placeholder implementation
            return api_response(false, 'Backend signing requires integration with blockchain node service', [], null, 501);
        } catch (\Exception $e) {
            Log::error('Failed to sign transaction', [
                'error' => $e->getMessage(),
                'transaction_id' => $request->validated('transaction_id'),
            ]);

            return api_response(false, 'Failed to sign transaction: ' . $e->getMessage(), [], null, 500);
        }
    }

    /**
     * POST /api/v1/transactions/record
     * Record an already-broadcast transaction (e.g. sent via MetaMask eth_sendTransaction)
     */
    public function record(\Illuminate\Http\Request $request): JsonResponse
    {
        $data = $request->validate([
            'tx_hash'      => 'required|string',
            'from_address' => 'required|string',
            'to_address'   => 'required|string',
            'chain_type'   => 'required|string|in:eth,bnb,polygon',
            'amount'       => 'required|string',
        ]);

        try {
            $wallet = \App\Models\Wallet::where('address', strtolower($data['from_address']))
                ->where('chain_type', $data['chain_type'])
                ->where('user_id', auth()->id())
                ->first();

            if (! $wallet && in_array($data['chain_type'], ['eth', 'bnb', 'polygon'], true)) {
                $wallet = \App\Models\Wallet::where('address', strtolower($data['from_address']))
                    ->where('user_id', auth()->id())
                    ->whereIn('chain_type', ['eth', 'bnb', 'polygon'])
                    ->where('is_active', true)
                    ->first();
            }

            if (! $wallet) {
                return api_response(false, 'Wallet not found', [], null, 404);
            }

            $transaction = \App\Models\Transaction::create([
                'wallet_id'      => $wallet->id,
                'user_id'        => auth()->id(),
                'tx_hash'        => $data['tx_hash'],
                'from_address'   => strtolower($data['from_address']),
                'to_address'     => $data['to_address'],
                'chain_type'     => $data['chain_type'],
                'amount'         => $data['amount'],
                'status'         => \App\Enums\TransactionStatus::SUBMITTED,
                'signing_method' => 'client',
                'submitted_at'   => now(),
            ]);

            return api_response(true, 'Transaction recorded', [
                'transaction' => new TransactionResource($transaction),
            ], null, 201);
        } catch (\Exception $e) {
            Log::error('Failed to record transaction', ['error' => $e->getMessage()]);
            return api_response(false, 'Failed to record transaction: ' . $e->getMessage(), [], null, 500);
        }
    }

    /**
     * POST /api/v1/transactions/broadcast
     * Broadcast signed transaction
     */
    public function broadcast(BroadcastTransactionRequest $request): JsonResponse
    {
        try {
            $transaction = Transaction::findOrFail($request->validated('transaction_id'));
            $signature = $request->validated('signature');

            $this->authorize('update', $transaction);

            $transaction = $this->transactionService->broadcastTransaction($transaction, $signature);

            return api_response(true, 'Transaction broadcast successfully', [
                'transaction' => new TransactionResource($transaction),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to broadcast transaction', [
                'error' => $e->getMessage(),
                'transaction_id' => $request->validated('transaction_id'),
            ]);

            return api_response(false, 'Failed to broadcast transaction: ' . $e->getMessage(), [], null, 500);
        }
    }

    /**
     * POST /api/v1/webhooks/alchemy
     * Alchemy webhook handler
     */
    public function alchemyWebhook(Request $request): JsonResponse
    {
        try {
            $secret = config('transaction.webhooks.alchemy_secret');

            // Validate webhook signature
            if ($secret && !$this->validateWebhookSignature($request, $secret)) {
                return api_response(false, 'Invalid webhook signature', [], null, 401);
            }

            $event = $request->all();

            // Process webhook asynchronously
            \App\Jobs\ProcessWebhookEventJob::dispatch($event, 'alchemy');

            return api_response(true, 'Webhook received');
        } catch (\Exception $e) {
            Log::error('Failed to process Alchemy webhook', [
                'error' => $e->getMessage(),
            ]);

            return api_response(false, 'Failed to process webhook', [], null, 500);
        }
    }

    /**
     * POST /api/v1/webhooks/etherscan
     * Etherscan webhook handler
     */
    public function etherscanWebhook(Request $request): JsonResponse
    {
        try {
            $secret = config('transaction.webhooks.etherscan_secret');

            // Validate webhook signature
            if ($secret && !$this->validateWebhookSignature($request, $secret)) {
                return api_response(false, 'Invalid webhook signature', [], null, 401);
            }

            $event = $request->all();

            // Process webhook asynchronously
            \App\Jobs\ProcessWebhookEventJob::dispatch($event, 'etherscan');

            return api_response(true, 'Webhook received');
        } catch (\Exception $e) {
            Log::error('Failed to process Etherscan webhook', [
                'error' => $e->getMessage(),
            ]);

            return api_response(false, 'Failed to process webhook', [], null, 500);
        }
    }

    private function validateWebhookSignature(Request $request, string $secret): bool
    {
        // In production, implement proper signature validation
        // This is a placeholder implementation
        return true;
    }
}
