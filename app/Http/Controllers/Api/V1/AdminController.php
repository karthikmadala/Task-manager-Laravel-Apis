<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\ApiLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Models\Wallet;
use App\Models\Transaction;
use App\Models\Token;
use App\Services\TransactionService;
use App\Enums\ChainType;
use App\Http\Resources\WalletResource;
use App\Services\PortfolioService;

class AdminController extends Controller
{
    /**
     * GET /api/v1/admin/users
     */
    public function users(Request $request): JsonResponse
    {
        $query = User::query();

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 20));

        return api_response(true, 'Users retrieved', [
            'users' => UserResource::collection($users),
            'pagination' => [
                'total'        => $users->total(),
                'per_page'     => $users->perPage(),
                'current_page' => $users->currentPage(),
                'last_page'    => $users->lastPage(),
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/logs
     */
    public function logs(Request $request): JsonResponse
    {
        $query = ApiLog::query();

        if ($statusCode = $request->query('status_code')) {
            $query->where('status_code', (int) $statusCode);
        }

        $logs = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 50));

        return api_response(true, 'Logs retrieved', [
            'logs' => $logs->map(fn (ApiLog $log) => [
                'id'          => $log->id,
                'method'      => $log->method,
                'path'        => $log->path,
                'status_code' => $log->status_code,
                'duration_ms' => $log->duration_ms,
                'ip_address'  => $log->ip,
                'created_at'  => $log->created_at?->toISOString(),
            ]),
        ]);
    }

    /**
     * GET /api/v1/admin/wallets
     *
     * Returns every wallet with its owner and portfolio data.
     */
    public function wallets(Request $request): JsonResponse
    {
        $chain   = $request->query('chain');
        $userId  = $request->query('user_id');
        $perPage = $request->query('per_page');

        $query = Wallet::with('user');

        if ($chain) {
            $chainEnum = ChainType::tryFrom(strtolower($chain));
            if (! $chainEnum) {
                return api_response(false, 'Invalid chain type.', [], null, 400);
            }
            $query->where('chain_type', $chainEnum->value);
        }

        if ($userId) {
            $query->where('user_id', (int) $userId);
        }

        $query->orderBy('created_at', 'desc');

        if ($perPage) {
            $wallets = $query->paginate((int) $perPage);
            $collection = $wallets->getCollection();
        } else {
            $collection = $query->get();
        }

        /** @var PortfolioService $portfolioService */
        $portfolioService = app(PortfolioService::class);

        $data = $collection->map(function (Wallet $wallet) use ($portfolioService) {
            $breakdown = $portfolioService->getWalletPortfolio($wallet, false);
            return [
                'wallet'    => new WalletResource($wallet),
                'user'      => new UserResource($wallet->user),
                'portfolio' => $breakdown,
            ];
        });

        $response = ['wallets' => $data];

        if (isset($wallets)) {
            $response['pagination'] = [
                'total'        => $wallets->total(),
                'per_page'     => $wallets->perPage(),
                'current_page' => $wallets->currentPage(),
                'last_page'    => $wallets->lastPage(),
            ];
        }

        return api_response(true, 'All wallets retrieved.', $response);
    }

    /**
     * GET /api/v1/admin/users/{user}
     *
     * Returns details for a specific user: user info, wallets with portfolio, and transactions (optionally filtered by chain).
     */
    public function userDetails(User $user, Request $request): JsonResponse
    {
        $chain = $request->query('chain');
        $chainEnum = null;
        if ($chain) {
            $chainEnum = ChainType::tryFrom(strtolower($chain));
            if (! $chainEnum) {
                return api_response(false, 'Invalid chain type.', [], null, 400);
            }
        }

        // Wallets
        $walletQuery = $user->wallets()->with('balances');
        if ($chainEnum) {
            $walletQuery->where('chain_type', $chainEnum->value);
        }
        $wallets = $walletQuery->get();

        /** @var PortfolioService $portfolioService */
        $portfolioService = app(PortfolioService::class);
        $walletData = $wallets->map(function (Wallet $wallet) use ($portfolioService) {
            $breakdown = $portfolioService->getWalletPortfolio($wallet, false);
            return [
                'wallet' => new WalletResource($wallet),
                'portfolio' => $breakdown,
            ];
        });

        // Transactions
        /** @var TransactionService $txService */
        $txService = app(TransactionService::class);
        $txFilters = [];
        if ($chainEnum) {
            $txFilters['chain'] = $chainEnum->value;
        }
        $transactionsPaginator = $txService->getUserTransactions($user, $txFilters);
        $transactions = $transactionsPaginator->map(function (Transaction $tx) {
            return [
                'id' => $tx->id,
                'tx_hash' => $tx->tx_hash,
                'from_address' => $tx->from_address,
                'to_address' => $tx->to_address,
                'amount' => $tx->amount,
                'chain_type' => $tx->chain_type->value,
                'status' => $tx->status->value,
                'signing_method' => $tx->signing_method,
                'gas_used' => $tx->gas_used,
                'gas_price_gwei' => $tx->gas_price_gwei,
                'gas_limit' => $tx->gas_limit,
                'fee_usd' => $tx->fee_usd,
                'block_number' => $tx->block_number,
                'confirmations_count' => $tx->confirmations_count,
                'error_message' => $tx->error_message,
                'submitted_at' => $tx->submitted_at?->toISOString(),
                'confirmed_at' => $tx->confirmed_at?->toISOString(),
                'created_at' => $tx->created_at?->toISOString(),
                'updated_at' => $tx->updated_at?->toISOString(),
                'token' => $tx->token ? [
                    'symbol' => $tx->token->symbol,
                    'name' => $tx->token->name,
                    'decimals' => $tx->token->decimals,
                ] : null,
            ];
        });

        return api_response(true, 'User details retrieved.', [
            'user' => new UserResource($user),
            'wallets' => $walletData,
            'transactions' => $transactions,
        ]);
    }

    /** GET /api/v1/admin/tokens */
    public function tokens(): JsonResponse
    {
        $tokens = Token::all();
        return api_response(true, 'Tokens retrieved', ['tokens' => $tokens]);
    }

    /** POST /api/v1/admin/tokens */
    public function createToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => 'required|string|max:10',
            'name' => 'required|string|max:100',
            'chain_type' => 'required|string',
            'contract' => 'nullable|string',
            'decimals' => 'required|integer',
            'current_price_usd' => 'nullable|numeric',
            'enabled' => 'required|boolean',
        ]);

        $attributes = [
            'symbol' => $validated['symbol'],
            'name' => $validated['name'],
            'chain_type' => $validated['chain_type'],
            'contract_address' => $validated['contract'] ?? null,
            'decimals' => $validated['decimals'],
            'current_price_usd' => $validated['current_price_usd'] ?? null,
            'enabled' => $validated['enabled'],
        ];

        $token = Token::withTrashed()
            ->where('symbol', $validated['symbol'])
            ->where('chain_type', $validated['chain_type'])
            ->first();

        if ($token) {
            $token->fill($attributes);

            if ($token->trashed()) {
                $token->restore();
            }

            $token->save();

            return api_response(true, 'Token restored', ['token' => $token]);
        }

        $token = Token::create($attributes);

        return api_response(true, 'Token created', ['token' => $token]);
    }

    /** PUT /api/v1/admin/tokens/{token} */
    public function updateToken(Request $request, Token $token): JsonResponse
    {
        $validated = $request->validate([
            'symbol' => 'sometimes|required|string|max:10',
            'name' => 'sometimes|required|string|max:100',
            'chain_type' => 'sometimes|required|string',
            'contract' => 'nullable|string',
            'decimals' => 'sometimes|required|integer',
            'current_price_usd' => 'nullable|numeric',
            'enabled' => 'sometimes|required|boolean',
        ]);
        $token->update([
            'symbol' => $validated['symbol'] ?? $token->symbol,
            'name' => $validated['name'] ?? $token->name,
            'chain_type' => $validated['chain_type'] ?? $token->chain_type,
            'contract_address' => $validated['contract'] ?? $token->contract_address,
            'decimals' => $validated['decimals'] ?? $token->decimals,
            'current_price_usd' => $validated['current_price_usd'] ?? $token->current_price_usd,
            'enabled' => $validated['enabled'] ?? $token->enabled,
        ]);
        return api_response(true, 'Token updated', ['token' => $token]);
    }

    /** DELETE /api/v1/admin/tokens/{token} */
    public function deleteToken(Token $token): JsonResponse
    {
        $token->delete();
        return api_response(true, 'Token deleted');
    }

    /** PATCH /api/v1/admin/tokens/{token}/status */
    public function toggleTokenStatus(Request $request, Token $token): JsonResponse
    {
        $validated = $request->validate(['enabled' => 'required|boolean']);
        $token->enabled = $validated['enabled'];
        $token->save();
        return api_response(true, 'Token status updated', ['token' => $token]);
    }

}
