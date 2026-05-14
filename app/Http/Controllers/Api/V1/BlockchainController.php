<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ChainType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Blockchain\BalanceRequest;
use App\Http\Requests\Api\V1\Blockchain\NativeBalanceRequest;
use App\Http\Requests\Api\V1\Blockchain\TokenDetailsRequest;
use App\Http\Requests\Api\V1\Blockchain\TransactionReceiptRequest;
use App\Services\Crypto\BlockchainInfoService;

/**
 * Read-only blockchain queries exposed as authenticated endpoints.
 * All write operations go through TransactionController.
 */
class BlockchainController extends Controller
{
    public function __construct(
        private readonly BlockchainInfoService $blockchain,
    ) {}

    /**
     * GET /blockchain/token-details?contract_address=0x...&chain=eth
     * Equivalent to Node GET /tokendetails
     */
    public function tokenDetails(TokenDetailsRequest $request)
    {
        $chain = ChainType::from($request->validated('chain'));
        $data = $this->blockchain->getTokenDetails(
            $request->validated('contract_address'),
            $chain
        );

        return api_response(true, 'Token details retrieved.', $data);
    }

    /**
     * POST /blockchain/balance
     * Equivalent to Node POST /balance
     */
    public function erc20Balance(BalanceRequest $request)
    {
        $chain = ChainType::from($request->validated('chain'));
        $data = $this->blockchain->getErc20Balance(
            $request->validated('address'),
            $request->validated('contract_address'),
            $chain
        );

        return api_response(true, 'ERC-20 balance retrieved.', $data);
    }

    /**
     * POST /blockchain/native-balance
     * Equivalent to Node POST /native_balance
     */
    public function nativeBalance(NativeBalanceRequest $request)
    {
        $chain = ChainType::from($request->validated('chain'));
        $data = $this->blockchain->getNativeBalance(
            $request->validated('address'),
            $chain
        );

        return api_response(true, 'Native balance retrieved.', $data);
    }

    /**
     * POST /blockchain/receipt
     * Equivalent to Node POST /getReceipt
     */
    public function transactionReceipt(TransactionReceiptRequest $request)
    {
        $chain = ChainType::from($request->validated('chain'));
        $data = $this->blockchain->getTransactionReceipt(
            $request->validated('hash'),
            $chain
        );

        if ($data === null) {
            return api_response(false, 'Transaction receipt not found or still pending.', null, null, 404);
        }

        return api_response(true, 'Transaction receipt retrieved.', $data);
    }

    /**
     * GET /blockchain/gas-price?chain=eth
     * Equivalent to Node POST /getCoinPrice
     */
    public function gasPrice(string $chain)
    {
        $chainEnum = ChainType::tryFrom($chain);

        if (!$chainEnum || !$chainEnum->isEvm()) {
            return api_response(false, 'Invalid or unsupported chain.', null, [
                'chain' => ['Must be one of: eth, bnb, polygon'],
            ], 422);
        }

        $data = $this->blockchain->getGasPrice($chainEnum);

        return api_response(true, 'Gas price retrieved.', $data);
    }
}
