<?php

namespace App\DTOs;

use App\Enums\ChainType;

class TransactionDto
{
    public function __construct(
        public readonly string $fromAddress,
        public readonly string $toAddress,
        public readonly ChainType $chain,
        public readonly string $amount,
        public readonly ?string $tokenAddress = null,
        public readonly ?string $contractAddress = null,
        public readonly ?string $method = null,
        public readonly ?array $methodParams = null,
        public readonly ?GasParameters $gasParams = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            fromAddress: $data['from_address'],
            toAddress: $data['to_address'],
            chain: ChainType::from($data['chain_type']),
            amount: $data['amount'],
            tokenAddress: $data['token_address'] ?? null,
            contractAddress: $data['contract_address'] ?? null,
            method: $data['method'] ?? null,
            methodParams: $data['method_params'] ?? null,
            gasParams: isset($data['gas_params']) ? GasParameters::fromArray($data['gas_params']) : null,
        );
    }

    public function toArray(): array
    {
        return [
            'from_address' => $this->fromAddress,
            'to_address' => $this->toAddress,
            'chain_type' => $this->chain->value,
            'amount' => $this->amount,
            'token_address' => $this->tokenAddress,
            'contract_address' => $this->contractAddress,
            'method' => $this->method,
            'method_params' => $this->methodParams,
            'gas_params' => $this->gasParams?->toArray(),
        ];
    }

    public function isNativeTransfer(): bool
    {
        return $this->tokenAddress === null && $this->contractAddress === null;
    }

    public function isErc20Transfer(): bool
    {
        return $this->tokenAddress !== null && $this->contractAddress === null;
    }

    public function isContractCall(): bool
    {
        return $this->contractAddress !== null;
    }
}