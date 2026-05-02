<?php

namespace App\DTOs;

class GasParameters
{
    public function __construct(
        public readonly ?string $gasLimit = null,
        public readonly ?string $gasPrice = null,
        public readonly ?string $maxFeePerGas = null,
        public readonly ?string $maxPriorityFeePerGas = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            gasLimit: $data['gas_limit'] ?? null,
            gasPrice: $data['gas_price'] ?? null,
            maxFeePerGas: $data['max_fee_per_gas'] ?? null,
            maxPriorityFeePerGas: $data['max_priority_fee_per_gas'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'gas_limit' => $this->gasLimit,
            'gas_price' => $this->gasPrice,
            'max_fee_per_gas' => $this->maxFeePerGas,
            'max_priority_fee_per_gas' => $this->maxPriorityFeePerGas,
        ];
    }

    public function isEip1559(): bool
    {
        return $this->maxFeePerGas !== null && $this->maxPriorityFeePerGas !== null;
    }

    public function isLegacy(): bool
    {
        return $this->gasPrice !== null;
    }
}