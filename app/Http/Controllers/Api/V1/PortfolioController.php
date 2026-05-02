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

        // Get all active wallets, then slice balances by requested chain
        $wallets = $user->wallets()
            ->where('is_active', true)
            ->with(['balances.token'])
            ->get();

        $totalUsd = '0';
        $walletData = $wallets->map(function (Wallet $wallet) use (&$totalUsd, $refresh, $chainEnum) {
            $breakdown = $this->portfolioService->getWalletPortfolio($wallet, $refresh);
            $balances = array_values(array_filter(
                $breakdown['balances'],
                fn (array $balance): bool => ($balance['chain_type'] ?? null) === $chainEnum->value
            ));

            if (empty($balances)) {
                return null;
            }

            $walletTotal = '0';
            foreach ($balances as $balance) {
                $walletTotal = bcadd($walletTotal, (string) ($balance['value_usd'] ?? '0'), 8);
            }

            $totalUsd = bcadd($totalUsd, $walletTotal, 8);

            return [
                'wallet' => $breakdown['wallet'],
                'balances' => $balances,
                'total_value_usd' => number_format((float) $walletTotal, 2, '.', ''),
            ];
        });

        return api_response(true, 'Chain portfolio retrieved.', [
            'total_value_usd' => number_format((float) $totalUsd, 2, '.', ''),
            'wallets'         => $walletData->filter()->values()->all(),
        ]);
    }
}
