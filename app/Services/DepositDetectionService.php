<?php

namespace App\Services;

use App\Enums\ChainType;
use App\Enums\TransactionStatus;
use App\Models\Token;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\Crypto\ExplorerService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepositDetectionService
{
    // Treat a deposit as confirmed after this many block confirmations.
    private const CONFIRMATION_THRESHOLD = 12;

    public function __construct(
        private readonly ExplorerService $explorer,
    ) {}

    /**
     * Scan one wallet for new incoming deposits since its last_synced_block watermark.
     * Updates the watermark on success.
     */
    public function scanWallet(Wallet $wallet): void
    {
        $chain = $wallet->chain_type;

        if ($chain === ChainType::BTC) {
            $this->scanBtcWallet($wallet);
            return;
        }

        $startBlock = (int) $wallet->last_synced_block;
        $currentBlock = $this->explorer->getCurrentBlockNumber($chain);

        if ($currentBlock === null) {
            Log::warning('DepositDetection: could not fetch current block', [
                'wallet_id' => $wallet->id,
                'chain'     => $chain->value,
            ]);
            return;
        }

        $currentBlockInt = (int) $currentBlock;

        if ($startBlock >= $currentBlockInt) {
            return; // nothing new
        }

        $this->processNativeDeposits($wallet, $chain, $startBlock, $currentBlockInt);
        $this->processTokenDeposits($wallet, $chain, $startBlock, $currentBlockInt);

        $wallet->update(['last_synced_block' => $currentBlockInt]);
    }

    // -------------------------------------------------------------------------

    private function processNativeDeposits(Wallet $wallet, ChainType $chain, int $startBlock, int $currentBlock): void
    {
        $txList = $this->explorer->getTransactionList(
            address: $wallet->address,
            chain: $chain,
            startBlock: $startBlock + 1,
            endBlock: $currentBlock,
            offset: 100,
            sort: 'asc',
        );

        $nativeToken = Token::where('chain_type', $chain->value)
            ->whereNull('contract_address')
            ->first();

        foreach ($txList as $tx) {
            if (strtolower((string) ($tx['to'] ?? '')) !== strtolower($wallet->address)) {
                continue; // outgoing, skip
            }

            if (($tx['isError'] ?? '0') !== '0') {
                continue; // failed on-chain
            }

            $this->upsertDeposit(
                wallet: $wallet,
                token: $nativeToken,
                txHash: (string) $tx['hash'],
                fromAddress: (string) ($tx['from'] ?? ''),
                amount: $this->toDecimalUnits((string) ($tx['value'] ?? '0'), 18),
                blockNumber: (int) ($tx['blockNumber'] ?? 0),
                confirmations: max(0, $currentBlock - (int) ($tx['blockNumber'] ?? 0)),
                chain: $chain,
            );
        }
    }

    private function processTokenDeposits(Wallet $wallet, ChainType $chain, int $startBlock, int $currentBlock): void
    {
        $transferList = $this->explorer->getTokenTransferList(
            address: $wallet->address,
            chain: $chain,
            startBlock: $startBlock + 1,
            endBlock: $currentBlock,
            offset: 100,
            sort: 'asc',
        );

        // Build contract→token map once to avoid per-row queries
        $contracts = collect($transferList)
            ->pluck('contractAddress')
            ->filter()
            ->map(fn ($c) => strtolower((string) $c))
            ->unique()
            ->values()
            ->all();

        $tokenMap = Token::where('chain_type', $chain->value)
            ->whereIn(DB::raw('LOWER(contract_address)'), $contracts)
            ->get()
            ->keyBy(fn (Token $t) => strtolower((string) $t->contract_address));

        foreach ($transferList as $tx) {
            if (strtolower((string) ($tx['to'] ?? '')) !== strtolower($wallet->address)) {
                continue; // outgoing, skip
            }

            $contract = strtolower((string) ($tx['contractAddress'] ?? ''));
            $token = $tokenMap->get($contract);

            if ($token === null) {
                continue; // unsupported token
            }

            $decimals = (int) ($tx['tokenDecimal'] ?? $token->decimals ?? 18);

            $this->upsertDeposit(
                wallet: $wallet,
                token: $token,
                txHash: (string) $tx['hash'],
                fromAddress: (string) ($tx['from'] ?? ''),
                amount: $this->toDecimalUnits((string) ($tx['value'] ?? '0'), $decimals),
                blockNumber: (int) ($tx['blockNumber'] ?? 0),
                confirmations: max(0, $currentBlock - (int) ($tx['blockNumber'] ?? 0)),
                chain: $chain,
            );
        }
    }

    /**
     * Insert deposit if not already recorded; update confirmation count if already known.
     */
    private function upsertDeposit(
        Wallet $wallet,
        ?Token $token,
        string $txHash,
        string $fromAddress,
        string $amount,
        int $blockNumber,
        int $confirmations,
        ChainType $chain,
    ): void {
        $status = $confirmations >= self::CONFIRMATION_THRESHOLD
            ? TransactionStatus::CONFIRMED
            : TransactionStatus::SUBMITTED;

        $existing = Transaction::where('tx_hash', $txHash)
            ->where('chain_type', $chain->value)
            ->first();

        if ($existing !== null) {
            if (! $existing->status->isTerminal()) {
                $existing->update([
                    'confirmations_count' => $confirmations,
                    'status'              => $status,
                    'confirmed_at'        => $status === TransactionStatus::CONFIRMED ? now() : null,
                ]);
            }
            return;
        }

        DB::transaction(function () use ($wallet, $token, $txHash, $fromAddress, $amount, $blockNumber, $confirmations, $chain, $status): void {
            Transaction::create([
                'wallet_id'          => $wallet->id,
                'user_id'            => $wallet->user_id,
                'token_id'           => $token?->id,
                'tx_hash'            => $txHash,
                'from_address'       => $fromAddress,
                'to_address'         => $wallet->address,
                'amount'             => $amount,
                'chain_type'         => $chain->value,
                'status'             => $status,
                'block_number'       => $blockNumber,
                'confirmations_count' => $confirmations,
                'confirmed_at'       => $status === TransactionStatus::CONFIRMED ? now() : null,
                'submitted_at'       => now(),
            ]);

            Log::info('Deposit detected', [
                'wallet_id'   => $wallet->id,
                'tx_hash'     => $txHash,
                'amount'      => $amount,
                'token'       => $token?->symbol ?? 'native',
                'chain'       => $chain->value,
                'block'       => $blockNumber,
                'confirmations' => $confirmations,
            ]);
        });
    }

    /**
     * Convert raw integer units from the explorer to decimal string with 18-digit precision.
     */
    private function toDecimalUnits(string $rawValue, int $decimals): string
    {
        if ($decimals === 0) {
            return $rawValue;
        }

        $divisor = bcpow('10', (string) $decimals, 0);

        return bcdiv($rawValue, $divisor, 18);
    }

    /**
     * BTC deposit detection via BlockCypher is a stub for now.
     * BlockCypher returns satoshis; divide by 1e8.
     */
    private function scanBtcWallet(Wallet $wallet): void
    {
        Log::info('DepositDetection: BTC scanning not yet implemented', [
            'wallet_id' => $wallet->id,
        ]);
    }
}
