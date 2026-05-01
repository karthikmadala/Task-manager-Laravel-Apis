<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\MetaMaskNonceRequest;
use App\Http\Requests\Wallet\MetaMaskVerifyRequest;
use App\Http\Requests\Wallet\StoreWalletRequest;
use App\Http\Resources\WalletResource;
use App\Models\Wallet;
use App\Enums\ChainType;
use Illuminate\Http\Request;
use App\Services\PortfolioService;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $walletService,
        private readonly PortfolioService $portfolioService
    )
    {
    }

    /** GET /api/v1/wallets */
    public function index(Request $request): JsonResponse
    {
        $chainParam = $request->query('chain');
        $refresh    = filter_var($request->query('refresh', false), FILTER_VALIDATE_BOOLEAN);
        $userId     = auth()->id();
        $walletsQuery = $this->walletService->listForUser($userId);

        if ($chainParam) {
            $chainEnum = ChainType::tryFrom(strtolower($chainParam));
            if (! $chainEnum) {
                return api_response(false, 'Invalid chain type.', [], null, 400);
            }
            $walletsQuery = $walletsQuery->filter(function ($wallet) use ($chainEnum) {
                return $wallet->chain_type->value === $chainEnum->value;
            });
        }

        $wallets = $walletsQuery instanceof \Illuminate\Support\Collection ? $walletsQuery : collect($walletsQuery);

        $walletData = $wallets->map(function (Wallet $wallet) use ($refresh) {
            // Auto-sync on first load if no balance records exist yet
            $shouldRefresh = $refresh || $wallet->balances->isEmpty();
            $breakdown = $this->portfolioService->getWalletPortfolio($wallet, $shouldRefresh);
            return [
                'wallet'    => new WalletResource($wallet),
                'portfolio' => $breakdown,
            ];
        });

        return api_response(true, 'Wallets retrieved.', [
            'wallets' => $walletData->values()->all(),
        ]);
    }

    /** POST /api/v1/wallets */
    public function store(StoreWalletRequest $request): JsonResponse
    {
        $this->authorize('create', Wallet::class);

        $wallet = $this->walletService->importExternal(auth()->user(), $request->validated());
        $this->portfolioService->syncWallet($wallet);

        return api_response(true, 'Wallet imported successfully.', [
            'wallet' => new WalletResource($wallet),
        ], null, 201);
    }

    /** DELETE /api/v1/wallets/{wallet} */
    public function destroy(Wallet $wallet): JsonResponse
    {
        $this->authorize('delete', $wallet);

        $this->walletService->remove($wallet);

        return api_response(true, 'Wallet removed.');
    }

    /** POST /api/v1/wallets/metamask/nonce  (authenticated — link flow) */
    public function metamaskNonce(MetaMaskNonceRequest $request): JsonResponse
    {
        $nonce = $this->walletService->generateLinkNonce(
            auth()->user(),
            $request->validated('address')
        );

        return api_response(true, 'Sign this nonce with MetaMask.', ['nonce' => $nonce]);
    }

    /** POST /api/v1/wallets/metamask/verify  (authenticated — link flow) */
    public function metamaskVerify(MetaMaskVerifyRequest $request): JsonResponse
    {
        $wallet = $this->walletService->verifyAndLink(
            auth()->user(),
            $request->validated('address'),
            $request->validated('signature')
        );

        return api_response(true, 'MetaMask wallet linked successfully.', [
            'wallet' => new WalletResource($wallet),
        ]);
    }
}
