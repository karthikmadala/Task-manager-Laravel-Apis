<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JsonResource;
use App\Enums\ChainType;
use App\Models\Token;
use App\Services\PortfolioService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChainInfoController extends Controller
{
    public function __construct(private readonly PortfolioService $portfolioService)
    {
    }

    /**
     * GET /api/v1/chain/{chain}/address/{address}/info
     * Returns on‑chain balances for the supplied address together with current USD prices.
     * Optional ?refresh=true forces a live RPC/explorer query.
     */
    public function info(Request $request, string $chain, string $address): JsonResponse
    {
        $chainEnum = ChainType::tryFrom(strtolower($chain));
        if (! $chainEnum) {
            return api_response(false, 'Invalid chain type.', [], null, 400);
        }

        $refresh = filter_var($request->query('refresh', false), FILTER_VALIDATE_BOOLEAN);
        // Delegate to PortfolioService which contains the RPC/explorer logic.
        $data = $this->portfolioService->getBalancesForAddress($address, $chainEnum, $refresh);

        info('Address data retrieved.', [
            'address' => $address,
            'chain'   => $chainEnum->value,
            'tokens'  => $data,
        ]);

        return api_response(true, 'Address data retrieved.', [
            'address' => $address,
            'chain'   => $chainEnum->value,
            'tokens'  => $data,
        ]);
    }

    /**
     * GET /api/v1/chains
     * Returns list of supported chains and their chain IDs.
     */
    public function index(): JsonResponse
    {
        // Retrieve chain IDs from native tokens in the tokens table
        $chains = Token::whereNull('contract_address')
            ->where('enabled', true)
            ->get()
            ->groupBy('chain_type')
            ->map(function ($tokens) {
                /** @var \App\Models\Token $token */
                $token = $tokens->first();
                return ['chain_id' => $token->chain_id];
            })
            ->toArray();
        return api_response(true, 'Supported chains retrieved.', $chains);
    }
}
