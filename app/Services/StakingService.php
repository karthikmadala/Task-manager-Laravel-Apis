<?php

namespace App\Services;

use App\Enums\ChainType;
use App\Exceptions\BlockchainException;
use App\Exceptions\StakingException;
use App\Services\Crypto\BlockchainNodeService;
use App\Support\Crypto\AbiEncoder;
use Illuminate\Support\Facades\Log;

/**
 * Staking contract interactions.
 *
 * Read operations (getUserDetails, getPlanDetails) are open to authenticated users.
 * Write operations (stake, withdraw) use MetaMask client-signing — this service
 * only prepares the transaction data. Backend-signed staking (from a service wallet)
 * is available via stakeFromServiceWallet() for admin/protocol operations.
 */
class StakingService
{
    // Contract function signatures
    private const SIG_STAKE              = 'stake(uint256,uint256)';
    private const SIG_WITHDRAW           = 'withdraw(uint256)';
    private const SIG_EMERGENCY_WITHDRAW = 'emergencyWithdraw(uint256)';
    private const SIG_GET_USER_DETAILS   = 'getUserDetails(address,uint256)';
    private const SIG_GET_PLAN_DETAILS   = 'getPlanDetails(uint256)';
    private const SIG_CLAIM_REWARDS      = 'claimRewards(uint256)';

    public function __construct(
        private readonly BlockchainNodeService $nodeService,
    ) {}

    /**
     * Prepare transaction parameters for client-side signing (MetaMask).
     * The user signs and broadcasts via the existing transaction flow.
     */
    public function prepareStakeTransaction(
        string $fromAddress,
        string $amountWei,
        int $level,
        ChainType $chain,
    ): array {
        $contractAddress = $this->contractAddress($chain);
        $callData = $this->encodeStake($amountWei, $level);
        $gasEstimate = $this->estimateGas($contractAddress, $callData, $fromAddress, $chain);

        return [
            'to'        => $contractAddress,
            'data'      => $callData,
            'value'     => '0x0',
            'gas'       => '0x' . dechex((int) $gasEstimate['gas_limit']),
            'gasPrice'  => $gasEstimate['gas_price_hex'],
            'from'      => $fromAddress,
            'chain'     => $chain->value,
            'operation' => 'stake',
            'level'     => $level,
        ];
    }

    /**
     * Prepare transaction parameters for withdraw (client-signed).
     *
     * @param  string  $type  'normal' or 'emergency'
     */
    public function prepareWithdrawTransaction(
        string $fromAddress,
        int $level,
        string $type,
        ChainType $chain,
    ): array {
        $contractAddress = $this->contractAddress($chain);
        $callData = $type === 'emergency'
            ? $this->encodeEmergencyWithdraw($level)
            : $this->encodeWithdraw($level);

        $gasEstimate = $this->estimateGas($contractAddress, $callData, $fromAddress, $chain);

        return [
            'to'        => $contractAddress,
            'data'      => $callData,
            'value'     => '0x0',
            'gas'       => '0x' . dechex((int) $gasEstimate['gas_limit']),
            'gasPrice'  => $gasEstimate['gas_price_hex'],
            'from'      => $fromAddress,
            'chain'     => $chain->value,
            'operation' => $type === 'emergency' ? 'emergency_withdraw' : 'withdraw',
            'level'     => $level,
        ];
    }

    /**
     * Get user staking details for a specific level.
     */
    public function getUserDetails(string $userAddress, int $level, ChainType $chain): array
    {
        $contractAddress = $this->contractAddress($chain);

        $callData = '0x' . bin2hex(
            AbiEncoder::encodeCall(
                self::SIG_GET_USER_DETAILS,
                [$userAddress, $level],
                ['address', 'uint256']
            )
        );

        try {
            $result = $this->nodeService->ethCall($chain, $contractAddress, $callData);

            return $this->decodeUserDetails($result, $level);
        } catch (\Throwable $e) {
            throw new StakingException("Failed to get user details: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Get staking plan configuration for a level.
     */
    public function getPlanDetails(int $level, ChainType $chain): array
    {
        $contractAddress = $this->contractAddress($chain);

        $callData = '0x' . bin2hex(
            AbiEncoder::encodeCall(
                self::SIG_GET_PLAN_DETAILS,
                [$level],
                ['uint256']
            )
        );

        try {
            $result = $this->nodeService->ethCall($chain, $contractAddress, $callData);

            return $this->decodePlanDetails($result, $level);
        } catch (\Throwable $e) {
            throw new StakingException("Failed to get plan details: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * Backend-signed stake via node_api_base (for service/protocol wallets only).
     * Uses STAKING_SIGNER_KEY loaded from keystore on the node service.
     */
    public function stakeFromServiceWallet(string $amountWei, int $level, ChainType $chain): string
    {
        $privateKey = config('crypto.staking.signer_key');

        if (!$privateKey) {
            throw new StakingException('STAKING_SIGNER_KEY not configured');
        }

        Log::info('Backend-signed stake', ['level' => $level, 'amount' => $amountWei, 'chain' => $chain->value]);

        // amount is in wei — convert to ETH units for node service
        $amountEth = (float) bcdiv($amountWei, bcpow('10', '18'), 18);

        return $this->nodeService->stake($chain, $privateKey, $amountEth, $level);
    }

    /**
     * Backend-signed withdraw via node_api_base.
     */
    public function withdrawFromServiceWallet(int $level, string $type, ChainType $chain): string
    {
        $privateKey = config('crypto.staking.signer_key');

        if (!$privateKey) {
            throw new StakingException('STAKING_SIGNER_KEY not configured');
        }

        Log::info('Backend-signed withdraw', ['level' => $level, 'type' => $type, 'chain' => $chain->value]);

        return $this->nodeService->withdraw($chain, $privateKey, $level, $type);
    }

    // ─── ABI encoding ────────────────────────────────────────────────────────

    private function encodeStake(string $amountWei, int $level): string
    {
        return '0x' . bin2hex(
            AbiEncoder::encodeCall(
                self::SIG_STAKE,
                [$amountWei, $level],
                ['uint256', 'uint256']
            )
        );
    }

    private function encodeWithdraw(int $level): string
    {
        return '0x' . bin2hex(
            AbiEncoder::encodeCall(
                self::SIG_WITHDRAW,
                [$level],
                ['uint256']
            )
        );
    }

    private function encodeEmergencyWithdraw(int $level): string
    {
        return '0x' . bin2hex(
            AbiEncoder::encodeCall(
                self::SIG_EMERGENCY_WITHDRAW,
                [$level],
                ['uint256']
            )
        );
    }

    // ─── Decoding ────────────────────────────────────────────────────────────

    private function decodeUserDetails(string $hexResult, int $level): array
    {
        $hex = ltrim($hexResult, '0x');

        if (strlen($hex) < 64) {
            return ['level' => $level, 'found' => false];
        }

        // UserDetail struct: level, amount, initialTime, endTime, rewardAmount,
        //                    withdrawAmount, status, rewardClaimed, lastClaimTime
        // + rewardAmount as second return value
        $chunks = str_split($hex, 64);

        return [
            'level'           => $this->hexToInt($chunks[0] ?? '0'),
            'amount'          => $this->hexToDecimal($chunks[1] ?? '0'),
            'initial_time'    => $this->hexToInt($chunks[2] ?? '0'),
            'end_time'        => $this->hexToInt($chunks[3] ?? '0'),
            'reward_amount'   => $this->hexToDecimal($chunks[4] ?? '0'),
            'withdraw_amount' => $this->hexToDecimal($chunks[5] ?? '0'),
            'status'          => $this->hexToInt($chunks[6] ?? '0') === 1,
            'reward_claimed'  => $this->hexToInt($chunks[7] ?? '0') === 1,
            'last_claim_time' => $this->hexToInt($chunks[8] ?? '0'),
            'claimable_reward'=> $this->hexToDecimal($chunks[9] ?? '0'),
            'found'           => true,
        ];
    }

    private function decodePlanDetails(string $hexResult, int $level): array
    {
        $hex = ltrim($hexResult, '0x');
        $chunks = str_split($hex, 64);

        return [
            'level'          => $level,
            'reward_percent' => $this->hexToInt($chunks[0] ?? '0'),
            'duration_limit' => $this->hexToInt($chunks[1] ?? '0'),
            'active'         => $this->hexToInt($chunks[2] ?? '0') === 1,
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function estimateGas(string $to, string $data, string $from, ChainType $chain): array
    {
        $gasPriceWei = $this->getGasPriceWei($chain);

        try {
            $gasLimit = (string) ((int) $this->nodeService->estimateGas($chain, $to, $from, $data) + 10000);
        } catch (\Throwable) {
            $gasLimit = '300000';
        }

        return [
            'gas_limit'     => $gasLimit,
            'gas_price_hex' => '0x' . dechex((int) $gasPriceWei),
        ];
    }

    private function getGasPriceWei(ChainType $chain): string
    {
        try {
            $data = $this->nodeService->getGasPrice($chain);
            return (string) ($data['gasPrice'] ?? (20 * 1_000_000_000));
        } catch (\Throwable) {
            return (string) (20 * 1_000_000_000);
        }
    }

    private function contractAddress(ChainType $chain): string
    {
        $address = config("crypto.contracts.staking.{$chain->value}");

        if (!$address) {
            throw new StakingException("Staking contract not configured for chain: {$chain->value}");
        }

        return $address;
    }

    private function hexToInt(string $hex): int
    {
        return (int) hexdec(ltrim($hex, '0'));
    }

    private function hexToDecimal(string $hex): string
    {
        $hex = ltrim($hex, '0');

        if ($hex === '') {
            return '0';
        }

        $dec = '0';
        $len = strlen($hex);

        for ($i = 0; $i < $len; $i++) {
            $dec = bcadd(bcmul($dec, '16'), (string) hexdec($hex[$i]));
        }

        return $dec;
    }
}
