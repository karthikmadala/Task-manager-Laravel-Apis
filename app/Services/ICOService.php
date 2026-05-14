<?php

namespace App\Services;

use App\Enums\ChainType;
use App\Exceptions\ICOException;
use App\Services\Crypto\BlockchainNodeService;
use App\Support\Crypto\AbiEncoder;
use Illuminate\Support\Facades\Log;

/**
 * ICO contract interactions.
 *
 * createPurchaseSignature() is the backend-signed authorization for token purchases.
 * prepareBuyTokensTransaction() returns tx data for client-side signing.
 */
class ICOService
{
    private const SIG_BUY_TOKEN = 'buyToken(address,uint256,uint256,(uint8,bytes32,bytes32,uint256))';

    public function __construct(
        private readonly BlockchainNodeService $nodeService,
    ) {}

    /**
     * Create a backend-signed authorization for an ICO token purchase.
     * Delegates to node_api_base (keystore wallet signs via /createSign).
     */
    public function createPurchaseSignature(
        int $index,
        string $recipientAddress,
        string $callerAddress,
        string $cryptoValue,
        ChainType $chain,
    ): array {
        $signerKey = config('crypto.ico.signer_key');

        if (!$signerKey) {
            throw new ICOException('ICO_SIGNER_KEY not configured');
        }

        $sig = $this->nodeService->createSign(
            $chain,
            $index,
            $recipientAddress,
            $callerAddress,
            $cryptoValue,
        );

        Log::info('ICO purchase signature created', [
            'index'     => $index,
            'recipient' => $recipientAddress,
            'caller'    => $callerAddress,
            'chain'     => $chain->value,
        ]);

        return [
            'v'     => $sig['v'],
            'r'     => '0x' . $sig['r'],
            's'     => '0x' . $sig['s'],
            'nonce' => time(),
        ];
    }

    /**
     * Prepare transaction data for buying ICO tokens via MetaMask.
     * The frontend signs and broadcasts the returned tx params.
     *
     * @param  string  $recipientAddress  Token recipient
     * @param  int     $paymentIndex      Payment method index (0=ETH, 1+=ERC-20)
     * @param  string  $tokenAmount       Amount of ICO tokens to buy (in token units)
     * @param  array   $signature         {v, r, s, nonce} from createPurchaseSignature()
     * @param  string  $ethValue          ETH value to send (wei, for ETH payments)
     */
    public function prepareBuyTokensTransaction(
        string $recipientAddress,
        int $paymentIndex,
        string $tokenAmount,
        array $signature,
        string $ethValue,
        ChainType $chain,
    ): array {
        $contractAddress = $this->contractAddress($chain);
        $callData = $this->encodeBuyToken($recipientAddress, $paymentIndex, $tokenAmount, $signature);
        $gasPriceWei = $this->getGasPriceWei($chain);

        return [
            'to'        => $contractAddress,
            'data'      => $callData,
            'value'     => '0x' . dechex((int) $ethValue),
            'gas'       => '0x' . dechex(200000),
            'gasPrice'  => '0x' . dechex((int) $gasPriceWei),
            'chain'     => $chain->value,
            'operation' => 'buy_tokens',
        ];
    }

    /**
     * Execute ICO token purchase from a service wallet via node_api_base.
     * Delegates signing and broadcast to the node service.
     */
    public function buyTokensFromServiceWallet(
        string $recipientAddress,
        int $paymentIndex,
        string $tokenAmount,
        array $signature,
        string $ethValue,
        ChainType $chain,
    ): string {
        $privateKey = config('crypto.ico.buyer_key');

        if (!$privateKey) {
            throw new ICOException('ICO_BUYER_KEY not configured');
        }

        Log::info('Backend-signed ICO token purchase', [
            'recipient'     => $recipientAddress,
            'payment_index' => $paymentIndex,
            'amount'        => $tokenAmount,
            'chain'         => $chain->value,
        ]);

        // ethValue in wei → ETH units for node service
        $amountEth = (float) bcdiv($ethValue, bcpow('10', '18'), 18);

        return $this->nodeService->buyIcoTokens(
            $chain,
            $privateKey,
            $recipientAddress,
            $paymentIndex,
            $amountEth,
            $signature,
        );
    }

    /**
     * Auto-sign and prepare a buyToken transaction for MetaMask signing.
     * Combines createPurchaseSignature() + prepareBuyTokensTransaction() in one call,
     * eliminating the need for a separate admin sign step.
     */
    public function purchaseTokensMetamask(
        string $recipientAddress,
        int $paymentIndex,
        string $tokenAmount,
        string $ethValue,
        ChainType $chain,
    ): array {
        // Use the ICO contract address as the caller for the signature
        $contractAddress = $this->contractAddress($chain);
        $signature = $this->createPurchaseSignature(
            $paymentIndex,
            $recipientAddress,
            $contractAddress,
            $ethValue,
            $chain,
        );

        return $this->prepareBuyTokensTransaction(
            $recipientAddress,
            $paymentIndex,
            $tokenAmount,
            $signature,
            $ethValue,
            $chain,
        );
    }

    // ─── ABI encoding ────────────────────────────────────────────────────────

    private function encodeBuyToken(
        string $recipient,
        int $paymentType,
        string $tokenAmount,
        array $sig,
    ): string {
        // buyToken(address recipient, uint256 paymentType, uint256 tokenAmount, Sign sign)
        // Sign = (uint8 v, bytes32 r, bytes32 s, uint256 nonce)
        $selector = AbiEncoder::functionSelector(self::SIG_BUY_TOKEN);

        $encoded = $selector
            . AbiEncoder::encodeAddress($recipient)
            . AbiEncoder::encodeUint($paymentType)
            . AbiEncoder::encodeUint($tokenAmount)
            . AbiEncoder::encodeSignStruct([
                'v'     => $sig['v'],
                'r'     => ltrim($sig['r'], '0x'),
                's'     => ltrim($sig['s'], '0x'),
                'nonce' => $sig['nonce'],
            ]);

        return '0x' . bin2hex($encoded);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function contractAddress(ChainType $chain): string
    {
        $address = config("crypto.contracts.ico.{$chain->value}");

        if (!$address) {
            throw new ICOException("ICO contract not configured for chain: {$chain->value}");
        }

        return $address;
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
}
