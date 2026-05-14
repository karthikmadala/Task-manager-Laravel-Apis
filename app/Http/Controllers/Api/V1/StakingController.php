<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ChainType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Staking\StakeRequest;
use App\Http\Requests\Api\V1\Staking\WithdrawRequest;
use App\Services\StakingService;
use Illuminate\Http\Request;

/**
 * Staking contract interactions.
 *
 * Prepare endpoints return unsigned transaction parameters for MetaMask signing.
 * Admin-only execute endpoints perform backend-signed operations using the service wallet.
 */
class StakingController extends Controller
{
    public function __construct(
        private readonly StakingService $staking,
    ) {}

    /**
     * GET /staking/user?address=0x...&level=0&chain=eth
     * Get user staking details for a specific level.
     */
    public function userDetails(Request $request)
    {
        $request->validate([
            'address' => ['required', 'string', 'regex:/^0x[a-fA-F0-9]{40}$/'],
            'level'   => ['required', 'integer', 'min:0'],
            'chain'   => ['required', 'string', 'in:eth,bnb,polygon'],
        ]);

        $chain = ChainType::from($request->input('chain'));
        $data = $this->staking->getUserDetails(
            $request->input('address'),
            (int) $request->input('level'),
            $chain
        );

        return api_response(true, 'Staking details retrieved.', $data);
    }

    /**
     * GET /staking/plan?level=0&chain=eth
     * Get staking plan configuration for a level.
     */
    public function planDetails(Request $request)
    {
        $request->validate([
            'level' => ['required', 'integer', 'min:0'],
            'chain' => ['required', 'string', 'in:eth,bnb,polygon'],
        ]);

        $chain = ChainType::from($request->input('chain'));
        $data = $this->staking->getPlanDetails(
            (int) $request->input('level'),
            $chain
        );

        return api_response(true, 'Staking plan retrieved.', $data);
    }

    /**
     * POST /staking/prepare/stake
     * Prepare an unsigned stake transaction for MetaMask signing.
     * Equivalent to Node POST /staking (client-signed variant).
     */
    public function prepareStake(StakeRequest $request)
    {
        $chain = ChainType::from($request->validated('chain'));
        $txParams = $this->staking->prepareStakeTransaction(
            $request->validated('from_address'),
            $request->validated('amount_wei'),
            (int) $request->validated('level'),
            $chain
        );

        return api_response(true, 'Stake transaction prepared. Sign and broadcast via /transactions/broadcast.', $txParams);
    }

    /**
     * POST /staking/prepare/withdraw
     * Prepare an unsigned withdraw transaction for MetaMask signing.
     * Equivalent to Node POST /e-withdraw (client-signed variant).
     */
    public function prepareWithdraw(WithdrawRequest $request)
    {
        $chain = ChainType::from($request->validated('chain'));
        $txParams = $this->staking->prepareWithdrawTransaction(
            $request->validated('from_address'),
            (int) $request->validated('level'),
            $request->validated('type'),
            $chain
        );

        return api_response(true, 'Withdraw transaction prepared. Sign and broadcast via /transactions/broadcast.', $txParams);
    }

    /**
     * POST /admin/staking/stake  (admin-only)
     * Backend-signed stake from the service wallet.
     */
    public function executeStake(StakeRequest $request)
    {
        $chain = ChainType::from($request->validated('chain'));
        $txHash = $this->staking->stakeFromServiceWallet(
            $request->validated('amount_wei'),
            (int) $request->validated('level'),
            $chain
        );

        return api_response(true, 'Stake transaction submitted.', ['tx_hash' => $txHash]);
    }

    /**
     * POST /admin/staking/withdraw  (admin-only)
     * Backend-signed withdraw from the service wallet.
     */
    public function executeWithdraw(WithdrawRequest $request)
    {
        $chain = ChainType::from($request->validated('chain'));
        $txHash = $this->staking->withdrawFromServiceWallet(
            (int) $request->validated('level'),
            $request->validated('type'),
            $chain
        );

        return api_response(true, 'Withdraw transaction submitted.', ['tx_hash' => $txHash]);
    }
}
