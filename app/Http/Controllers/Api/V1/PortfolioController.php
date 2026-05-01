<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\PortfolioResource;
use App\Models\Wallet;
use App\Enums\ChainType;
use App\Services\PortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function __construct(private readonly PortfolioService $portfolioService)
    {
    }

    /**
     * GET /api/v1/portfolio
     * Returns aggregated USD portfolio across all user wallets.
     * Pass ?refresh=true to trigger a live blockchain sync first.
     */
    public function index(Request $request): JsonResponse
    {
        $refresh   = filter_var($request->query('refresh', false), FILTER_VALIDATE_BOOLEAN);
        $portfolio = $this->portfolioService->getPortfolio($request->user(), $refresh);

        return api_response(true, 'Portfolio retrieved.', [
            'portfolio' => new PortfolioResource($portfolio),
        ]);
    }

    /**
     * GET /api/v1/portfolio/{wallet}
     * Returns token breakdown for a single wallet.
     * Pass ?refresh=true to trigger a live sync.
     */
    public function show(Request $request, Wallet $wallet): JsonResponse
    {
        $this->authorize('view', $wallet);

        $refresh   = filter_var($request->query('refresh', false), FILTER_VALIDATE_BOOLEAN);
        $breakdown = $this->portfolioService->getWalletPortfolio($wallet, $refresh);

        return api_response(true, 'Wallet portfolio retrieved.', [
            'portfolio' => $breakdown,
        ]);
    }
    
    /**
     * GET /api/v1/portfolio/chain/{chain}
     * Returns aggregated USD portfolio for all active wallets on the given chain.
     * Optional ?refresh=true to force a live sync.
     */
    public function chain(Request $request, string $chain): JsonResponse
    {
        $refresh = filter_var($request->query('refresh', false), FILTER_VALIDATE_BOOLEAN);
        $user = $request->user();

        // Validate chain type
        $chainEnum = ChainType::tryFrom(strtolower($chain));
        if (! $chainEnum) {
            return api_response(false, 'Invalid chain type.', [], null, 400);
        }

        // Get all active wallets for the requested chain
        $wallets = $user->wallets()
            ->where('is_active', true)
            ->where('chain_type', $chainEnum->value)
            ->with(['balances.token'])
            ->get();

        $totalUsd = '0';
        $walletData = $wallets->map(function (Wallet $wallet) use (&$totalUsd, $refresh) {
            $breakdown = $this->portfolioService->getWalletPortfolio($wallet, $refresh);
            $totalUsd  = bcadd($totalUsd, $breakdown['total_value_usd'], 8);

            return $breakdown;
        });

        return api_response(true, 'Chain portfolio retrieved.', [
            'total_value_usd' => number_format((float) $totalUsd, 2, '.', ''),
            'wallets'         => $walletData->values()->all(),
        ]);
    }
}
