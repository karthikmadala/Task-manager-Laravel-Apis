<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Wallet;
use App\Services\Crypto\WalletGenerationService;
use App\Enums\ChainType;
use App\Enums\WalletType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wallet and key generation endpoints.
 * These are authenticated — only logged-in users can generate wallets.
 */
class WalletGenerationController extends Controller
{
    public function __construct(
        private readonly WalletGenerationService $walletGen,
    ) {}

    /**
     * GET /wallet-gen/mnemonic
     * Generate a BIP-39 12-word recovery phrase.
     */
    public function mnemonic(): JsonResponse
    {
        $phrase = $this->walletGen->generateMnemonic();

        return api_response(true, 'Recovery phrase generated. Store it securely — it will not be shown again.', [
            'phrase' => $phrase,
        ]);
    }

    /**
     * POST /wallet-gen/address
     * Generate a new EVM wallet address (without exposing the private key).
     * Creates a watch-only wallet — no signing capability.
     */
    public function createAddress(): JsonResponse
    {
        $address = $this->walletGen->createAddress();

        return api_response(true, 'Wallet address generated.', [
            'address' => $address,
        ]);
    }

    /**
     * POST /wallet-gen/internal
     * Generate a key pair, encrypt the private key with user's spending password,
     * and save it as an internal wallet in the database.
     * User can sign transactions with this wallet.
     */
    public function createInternalWallet(Request $request): JsonResponse
    {
        $request->validate([
            'password'   => 'required|string|min:8',
            'chain_type' => 'required|string|in:eth,bnb,polygon',
            'label'      => 'nullable|string|max:100',
        ]);

        $internalWalletCount = \App\Models\Wallet::where('user_id', auth()->id())
            ->where('wallet_origin', 'internal')
            ->count();

        if ($internalWalletCount >= 3) {
            return api_response(false, 'You have reached the maximum of 3 created wallets per account.', null, [
                'wallet' => ['Maximum wallet limit reached.'],
            ], 422);
        }

        $pair = $this->walletGen->createAccountWithKey();

        $wallet = new Wallet([
            'user_id'   => auth()->id(),
            'chain_type' => ChainType::from($request->input('chain_type')),
            'wallet_type' => WalletType::EXTERNAL,
            'address'   => $pair['address'],
            'label'     => $request->input('label'),
            'is_active' => true,
        ]);

        $wallet->setEncryptedPrivateKey($pair['private_key'], $request->input('password'));
        $wallet->save();

        return api_response(true, 'Internal wallet created. Keep your password safe — it\'s needed to sign transactions.', [
            'wallet' => [
                'id'         => $wallet->id,
                'address'    => $wallet->address,
                'chain_type' => $wallet->chain_type->value,
                'label'      => $wallet->label,
                'has_private_key' => true,
            ],
        ], null, 201);
    }

    /**
     * POST /wallet-gen/reveal-key
     * Decrypt and reveal the private key of an internal wallet.
     * Requires the user's spending password.
     */
    public function revealKey(Request $request): JsonResponse
    {
        $request->validate([
            'wallet_id' => 'required|uuid',
            'password'  => 'required|string',
        ]);

        $wallet = Wallet::where('id', $request->input('wallet_id'))
            ->where('user_id', auth()->id())
            ->where('wallet_origin', 'internal')
            ->first();

        if (! $wallet) {
            return api_response(false, 'Wallet not found or does not have an internal key.', null, null, 404);
        }

        if (! $wallet->hasPrivateKey()) {
            return api_response(false, 'This wallet does not have an internal private key stored.', null, null, 400);
        }

        $privateKey = $wallet->decryptPrivateKey($request->input('password'));

        if ($privateKey === null) {
            return api_response(false, 'Incorrect password. Please try again.', null, null, 401);
        }

        return api_response(true, 'Private key revealed. Store it securely — it will not be shown again.', [
            'address'     => $wallet->address,
            'private_key' => $privateKey,
        ]);
    }

    /**
     * POST /admin/wallet-gen/keypair  (admin-only)
     * Generate a full key pair — address + private key.
     * Restricted to admin role; use only for protocol/service wallets.
     */
    public function createKeypair(): JsonResponse
    {
        $pair = $this->walletGen->createAccountWithKey();

        return api_response(true, 'Key pair generated. Store the private key immediately — it will not be shown again.', [
            'address'     => $pair['address'],
            'private_key' => $pair['private_key'],
        ]);
    }
}
