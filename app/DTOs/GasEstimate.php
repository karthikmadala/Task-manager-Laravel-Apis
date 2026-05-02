<?php

namespace App\DTOs;

class GasEstimate
{
    public function __construct(
        public readonly string $gasLimit,
        public readonly string $gasPrice,
        public readonly ?string $maxFeePerGas = null,
        public readonly ?string $maxPriorityFeePerGas = null,
        public readonly string $estimatedCost = '0',
        public readonly string $estimatedCostUsd = '0',
        public readonly int $estimatedTimeSeconds = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            gasLimit: $data['gas_limit'],
            gasPrice: $data['gas_price'],
            maxFeePerGas: $data['max_fee_per_gas'] ?? null,
            maxPriorityFeePerGas: $data['max_priority_fee_per_gas'] ?? null,
            estimatedCost: $data['estimated_cost'] ?? '0',
            estimatedCostUsd: $data['estimated_cost_usd'] ?? '0',
            estimatedTimeSeconds: $data['estimated_time_seconds'] ?? 0,
        );
    }

    public function toArray(): array
    {
        return [
            'gas_limit' => $this->gasLimit,
            'gas_price' => $this->gasPrice,
            'max_fee_per_gas' => $this->maxFeePerGas,
            'max_priority_fee_per_gas' => $this->maxPriorityFeePerGas,
            'estimated_cost' => $this->estimatedCost,
            'estimated_cost_usd' => $this->estimatedCostUsd,
            'estimated_time_seconds' => $this->estimatedTimeSeconds,
        ];
    }

    public function isEip1559(): bool
    {
        return $this->maxFeePerGas !== null && $this->maxPriorityFeePerGas !== null;
    }
}