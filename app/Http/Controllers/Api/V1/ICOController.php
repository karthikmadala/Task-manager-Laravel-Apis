<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ChainType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ICO\BuyTokensRequest;
use App\Http\Requests\Api\V1\ICO\CreateSignRequest;
use App\Http\Requests\Api\V1\ICO\SelfServiceBuyRequest;
use App\Services\ICOService;

/**
 * ICO contract endpoints.
 *
 * createSign() — admin-only, uses the backend signer key to authorize a purchase.
 * prepareBuyTokens() — authenticated user, prepares tx for MetaMask signing.
 * executeBuyTokens() — admin-only, backend-signed purchase from service wallet.
 */
class ICOController extends Controller
{
    public function __construct(
        private readonly ICOService $ico,
    ) {}

    /**
     * POST /admin/ico/sign  (admin-only)
     * Create a backend-signed authorization for an ICO token purchase.
     * Equivalent to Node POST /createSign
     */
    public function createSign(CreateSignRequest $request)
    {
        $chain = ChainType::from($request->validated('chain'));
        $signature = $this->ico->createPurchaseSignature(
            (int) $request->validated('index'),
            $request->validated('address'),
            $request->validated('caller'),
            $request->validated('crypto_value'),
            $chain
        );

        return api_response(true, 'Purchase signature created.', ['signature' => $signature]);
    }

    /**
     * POST /ico/buy/prepare
     * Prepare unsigned buyToken transaction for MetaMask signing.
     * Frontend calls createSign first, then this endpoint.
     */
    public function prepareBuyTokens(BuyTokensRequest $request)
    {
        $chain = ChainType::from($request->validated('chain'));
        $txParams = $this->ico->prepareBuyTokensTransaction(
            $request->validated('address'),
            (int) $request->validated('payment_index'),
            $request->validated('amount'),
            $request->validated('signature'),
            $request->validated('eth_value'),
            $chain
        );

        return api_response(true, 'Buy-tokens transaction prepared. Sign and broadcast via /transactions/broadcast.', $txParams);
    }

    /**
     * POST /ico/buy  (authenticated user, throttle:broadcast)
     * Combined sign + prepare for self-service ICO purchase.
     * Auto-generates the backend signature and returns unsigned tx params.
     * User signs and broadcasts the returned tx params via MetaMask.
     */
    public function selfServiceBuyTokens(SelfServiceBuyRequest $request)
    {
        $chain = ChainType::from($request->validated('chain'));
        $txParams = $this->ico->purchaseTokensMetamask(
            $request->validated('address'),
            (int) $request->validated('payment_index'),
            $request->validated('amount'),
            $request->validated('eth_value'),
            $chain,
        );

        return api_response(true, 'ICO transaction prepared. Sign and broadcast via MetaMask.', $txParams);
    }

    /**
     * POST /admin/ico/buy  (admin-only)
     * Execute token purchase from the service wallet (backend-signed).
     * Equivalent to Node POST /buyTokens
     */
    public function executeBuyTokens(BuyTokensRequest $request)
    {
        $chain = ChainType::from($request->validated('chain'));
        $txHash = $this->ico->buyTokensFromServiceWallet(
            $request->validated('address'),
            (int) $request->validated('payment_index'),
            $request->validated('amount'),
            $request->validated('signature'),
            $request->validated('eth_value'),
            $chain
        );

        return api_response(true, 'Buy-tokens transaction submitted.', ['tx_hash' => $txHash]);
    }
}
