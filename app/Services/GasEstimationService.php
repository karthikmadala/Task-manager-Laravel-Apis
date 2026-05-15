<?php

namespace App\Services;

use App\DTOs\GasEstimate;
use App\DTOs\TransactionDto;
use App\Enums\ChainType;
use App\Exceptions\GasEstimationFailedException;
use App\Models\Token;
use App\Services\Crypto\BlockchainNodeService;
use App\Services\Crypto\ExplorerService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GasEstimationService
{
    public function __construct(
        private readonly ExplorerService $explorerService,
        private readonly BlockchainNodeService $node,
    ) {}

    public function estimateGas(TransactionDto $dto): GasEstimate
    {
        if (! $dto->chain->isEvm()) {
            throw new GasEstimationFailedException('Gas estimation not supported for this chain');
        }

        try {
            $oracle = $this->getGasOracle($dto->chain);
            $gasLimit = $this->estimateGasLimit($dto);

            $safetyMargin = (string) config('transaction.gas.safety_margin', 1.2);
            $adjustedGasLimit = bcmul($gasLimit, $safetyMargin, 0);

            [$maxFeePerGas, $maxPriorityFeePerGas] = $this->eip1559Fees($oracle);
            $estimatedCostWei = $this->estimateCostInWei($adjustedGasLimit, $oracle['propose']);
            $estimatedCostUsd = $this->estimateCostInUsd($estimatedCostWei, $dto->chain);

            return new GasEstimate(
                gasLimit: $adjustedGasLimit,
                gasPrice: $oracle['propose'],
                maxFeePerGas: $maxFeePerGas,
                maxPriorityFeePerGas: $maxPriorityFeePerGas,
                estimatedCost: $estimatedCostWei,
                estimatedCostUsd: $estimatedCostUsd,
                estimatedTimeSeconds: $this->estimateConfirmationTime($dto->chain),
            );
        } catch (\Exception $e) {
            Log::error('Gas estimation failed', [
                'error' => $e->getMessage(),
                'chain' => $dto->chain->value,
                'from' => $dto->fromAddress,
                'to' => $dto->toAddress,
            ]);

            throw new GasEstimationFailedException('Failed to estimate gas: ' . $e->getMessage());
        }
    }

    /**
     * Returns the full cached gas oracle (safe/propose/fast/base_fee) for a chain.
     *
     * @return array{safe: string, propose: string, fast: string, base_fee: string}
     */
    public function getGasOracle(ChainType $chain): array
    {
        $cacheKey = "gas:oracle:{$chain->value}";
        $cacheTtl = config('transaction.gas.cache_ttl', 30);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($chain) {
            // Try node_api_base gas oracle first (Etherscan via node)
            $oracle = $this->node->getGasOracle($chain);

            if ($oracle !== null) {
                return $oracle;
            }

            // Fallback to direct explorer call
            $oracle = $this->explorerService->getGasOracle($chain);

            if ($oracle !== null) {
                return $oracle;
            }

            Log::warning('Gas oracle unavailable, using defaults', ['chain' => $chain->value]);

            $default = $this->getDefaultGasPrice($chain);

            return ['safe' => $default, 'propose' => $default, 'fast' => $default, 'base_fee' => '0'];
        });
    }

    public function getCurrentGasPrice(ChainType $chain): string
    {
        return $this->getGasOracle($chain)['propose'];
    }

    public function estimateContractCall(string $contract, string $method, array $params, ChainType $chain): GasEstimate
    {
        try {
            $gasLimit = $this->estimateProxyGas([
                'to' => $contract,
                'data' => $this->encodeMethodCall($method, $params),
            ], $chain);

            $oracle = $this->getGasOracle($chain);
            [$maxFeePerGas, $maxPriorityFeePerGas] = $this->eip1559Fees($oracle);
            $estimatedCostWei = $this->estimateCostInWei($gasLimit, $oracle['propose']);
            $estimatedCostUsd = $this->estimateCostInUsd($estimatedCostWei, $chain);

            return new GasEstimate(
                gasLimit: $gasLimit,
                gasPrice: $oracle['propose'],
                maxFeePerGas: $maxFeePerGas,
                maxPriorityFeePerGas: $maxPriorityFeePerGas,
                estimatedCost: $estimatedCostWei,
                estimatedCostUsd: $estimatedCostUsd,
                estimatedTimeSeconds: $this->estimateConfirmationTime($chain),
            );
        } catch (\Exception $e) {
            Log::error('Contract call gas estimation failed', [
                'error' => $e->getMessage(),
                'contract' => $contract,
                'method' => $method,
                'chain' => $chain->value,
            ]);

            throw new GasEstimationFailedException('Failed to estimate contract call gas: ' . $e->getMessage());
        }
    }

    public function buildMetaMaskTxParams(TransactionDto $dto, GasEstimate $gasEstimate): array
    {
        $params = [
            'from' => $dto->fromAddress,
            'gas'  => '0x' . dechex((int) $gasEstimate->gasLimit),
        ];

        if ($gasEstimate->maxFeePerGas && $gasEstimate->maxPriorityFeePerGas) {
            $params['maxFeePerGas']         = $this->gweiToHex($gasEstimate->maxFeePerGas);
            $params['maxPriorityFeePerGas'] = $this->gweiToHex($gasEstimate->maxPriorityFeePerGas);
        }

        if ($dto->tokenAddress) {
            $params['to']    = $dto->tokenAddress;
            $params['value'] = '0x0';
            $params['data']  = $this->encodeErc20Transfer($dto->toAddress, $dto->amount, $dto->tokenAddress);
        } else {
            $params['to']    = $dto->toAddress;
            $params['value'] = $this->decimalAmountToHex($dto->amount, 18);
        }

        return $params;
    }

    private function gweiToHex(string $gwei): string
    {
        $wei = bcmul($gwei, '1000000000', 0);
        return '0x' . $this->decimalToHex($wei);
    }

    public function validateGasParameters(array $params): bool
    {
        $gasLimit = $params['gas_limit'] ?? null;
        $gasPrice = $params['gas_price'] ?? null;

        if ($gasLimit !== null) {
            $maxLimit = config('transaction.gas.max_limit', 1000000);
            if (bccomp($gasLimit, (string) $maxLimit, 0) > 0) {
                return false;
            }
        }

        if ($gasPrice !== null && bccomp($gasPrice, '0', 9) <= 0) {
            return false;
        }

        return true;
    }

    private function estimateGasLimit(TransactionDto $dto): string
    {
        if ($dto->isNativeTransfer()) {
            return '21000';
        }

        $callData = ['from' => $dto->fromAddress];

        if ($dto->tokenAddress) {
            $callData['to'] = $dto->tokenAddress;
            $callData['data'] = $this->encodeErc20Transfer($dto->toAddress, $dto->amount, $dto->tokenAddress);
        } elseif ($dto->contractAddress) {
            $callData['to'] = $dto->contractAddress;
            $callData['data'] = $this->encodeMethodCall($dto->method ?? '', $dto->methodParams ?? []);
        } else {
            $callData['to'] = $dto->toAddress;
        }

        return $this->estimateProxyGas($callData, $dto->chain);
    }

    /**
     * @param  array<string, string>  $callData
     */
    private function estimateProxyGas(array $callData, ChainType $chain): string
    {
        try {
            $gasLimit = $this->explorerService->estimateEvmGas($callData, $chain);

            if ($gasLimit !== null && bccomp($gasLimit, '0', 0) > 0) {
                return $gasLimit;
            }

            throw new \RuntimeException('Explorer proxy returned no gas estimate');
        } catch (\Exception $e) {
            Log::warning('Explorer gas estimate failed, using fallback gas limit', [
                'chain' => $chain->value,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackGasLimit($callData);
        }
    }

    /**
     * @param  array<string, string>  $callData
     */
    private function fallbackGasLimit(array $callData): string
    {
        if (isset($callData['data'])) {
            return '65000';
        }

        return (string) config('transaction.gas.default_limit', 21000);
    }

    private function estimateCostInWei(string $gasLimit, string $gasPriceGwei): string
    {
        $gasPriceWei = bcmul($gasPriceGwei, '1000000000', 0);

        return bcmul($gasLimit, $gasPriceWei, 0);
    }

    private function estimateCostInUsd(string $costInWei, ChainType $chain): string
    {
        $nativeUsd = $this->explorerService->getNativeTokenPriceUsd($chain);

        if ($nativeUsd === null || $nativeUsd <= 0) {
            return '0';
        }

        $costInNative = bcdiv($costInWei, '1000000000000000000', 18);

        return bcmul($costInNative, number_format($nativeUsd, 8, '.', ''), 8);
    }

    /**
     * Derive EIP-1559 fee fields from a gas oracle response.
     * maxFeePerGas  = fast price (cap the user will pay per gas)
     * maxPriorityFeePerGas = fast - baseFee (miner tip portion)
     *
     * @param  array{safe: string, propose: string, fast: string, base_fee: string}  $oracle
     * @return array{0: string, 1: string}
     */
    private function eip1559Fees(array $oracle): array
    {
        $maxFeePerGas = $oracle['fast'];
        $priority = bcsub($oracle['fast'], $oracle['base_fee'], 9);

        if (bccomp($priority, '1', 9) < 0) {
            $priority = '1';
        }

        return [$maxFeePerGas, $priority];
    }

    private function estimateConfirmationTime(ChainType $chain): int
    {
        return match ($chain) {
            ChainType::ETH => 15,
            ChainType::BNB => 3,
            ChainType::POLYGON => 5,
            default => 30,
        };
    }

    private function getDefaultGasPrice(ChainType $chain): string
    {
        return match ($chain) {
            ChainType::ETH => '20',
            ChainType::BNB => '5',
            ChainType::POLYGON => '30',
            default => '20',
        };
    }

    private function encodeErc20Transfer(string $toAddress, string $amount, string $tokenAddress): string
    {
        $methodId = '0xa9059cbb';
        $paddedAddress = str_pad(substr($toAddress, 2), 64, '0', STR_PAD_LEFT);
        $decimals = $this->resolveTokenDecimals($tokenAddress);
        $amountHex = $this->decimalAmountToHex($amount, $decimals);
        $paddedAmount = str_pad(substr($amountHex, 2), 64, '0', STR_PAD_LEFT);

        return $methodId . $paddedAddress . $paddedAmount;
    }

    private function encodeMethodCall(string $method, array $params): string
    {
        return '0x';
    }

    private function resolveTokenDecimals(string $tokenAddress): int
    {
        return (int) (Token::whereRaw('LOWER(contract_address) = ?', [strtolower($tokenAddress)])
            ->value('decimals') ?? 18);
    }

    private function decimalAmountToHex(string $amount, int $decimals): string
    {
        $parts = explode('.', $amount, 2);
        $whole = $parts[0] ?? '0';
        $fraction = $parts[1] ?? '';
        $normalizedWhole = preg_replace('/\D/', '', $whole) ?: '0';
        $normalizedFraction = preg_replace('/\D/', '', $fraction) ?: '';
        $paddedFraction = str_pad(substr($normalizedFraction, 0, $decimals), $decimals, '0');
        $base = bcpow('10', (string) $decimals, 0);
        $units = bcadd(bcmul($normalizedWhole, $base, 0), $paddedFraction === '' ? '0' : $paddedFraction, 0);

        return '0x' . $this->decimalToHex($units);
    }

    private function decimalToHex(string $decimal): string
    {
        if ($decimal === '0') {
            return '0';
        }

        $hex = '';

        while (bccomp($decimal, '0', 0) > 0) {
            $remainder = (int) bcmod($decimal, '16');
            $hex = dechex($remainder) . $hex;
            $decimal = bcdiv($decimal, '16', 0);
        }

        return $hex;
    }
}
