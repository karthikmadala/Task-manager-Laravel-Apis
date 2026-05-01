<?php

namespace App\Services;

use App\Enums\ChainType;
use App\Enums\WalletType;
use App\Models\User;
use App\Models\Wallet;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Services\Crypto\EthereumSignatureService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function __construct(
        private readonly WalletRepositoryInterface $walletRepository,
        private readonly EthereumSignatureService $signatureService,
    ) {}

    /**
     * Return all wallets for the authenticated user.
     */
    public function listForUser(int $userId): Collection
    {
        return $this->walletRepository->allForUser($userId);
    }

    /**
     * Import an external (watch-only) wallet after validating its address format.
     */
    public function importExternal(User $user, array $data): Wallet
    {
        $chain = ChainType::from($data['chain_type']);

        $this->validateAddress($data['address'], $chain);
        $this->ensureNotDuplicate($data['address'], $user->id, $chain);

        return $this->walletRepository->create([
            'user_id'     => $user->id,
            'chain_type'  => $chain->value,
            'wallet_type' => WalletType::EXTERNAL->value,
            'address'     => strtolower($data['address']),
            'label'       => $data['label'] ?? null,
            'is_active'   => true,
        ]);
    }

    /**
     * Generate a nonce for an authenticated user to sign with MetaMask
     * (used when linking a new MetaMask wallet to an existing account).
     */
    public function generateLinkNonce(User $user, string $address): string
    {
        $address = strtolower($address);
        $chain = ChainType::ETH; // MetaMask always signs EVM addresses
        $this->validateAddress($address, $chain);
        $existingWallet = $this->walletRepository->findByAddressAndUser($address, $user->id, $chain->value);

        if ($existingWallet && $existingWallet->is_active) {
            throw ValidationException::withMessages([
                'address' => ['This wallet address is already linked to your account.'],
            ]);
        }

        $nonce = $this->signatureService->generateNonce();

        $wallet = $existingWallet;

        if (! $wallet) {
            $wallet = $this->walletRepository->create([
                'user_id'     => $user->id,
                'chain_type'  => ChainType::ETH->value,
                'wallet_type' => WalletType::METAMASK->value,
                'address'     => $address,
                'is_active'   => false, // activated after signature verified
            ]);
        }

        $this->walletRepository->updateNonce($wallet, $nonce);

        return $nonce;
    }

    /**
     * Verify a MetaMask signature and activate the wallet link.
     */
    public function verifyAndLink(User $user, string $address, string $signature): Wallet
    {
        $address = strtolower($address);
        $wallet = $this->walletRepository->findByAddressAndUser($address, $user->id, ChainType::ETH->value);

        if (! $wallet || ! $wallet->getRawOriginal('metamask_nonce')) {
            throw ValidationException::withMessages([
                'address' => ['No pending nonce found. Request a new nonce first.'],
            ]);
        }

        // getRawOriginal bypasses the $hidden guard to access the nonce
        $nonce = $wallet->getRawOriginal('metamask_nonce');

        if (! $this->signatureService->verifySignature($nonce, $signature, $address)) {
            throw ValidationException::withMessages([
                'signature' => ['Signature verification failed.'],
            ]);
        }

        return DB::transaction(function () use ($wallet): Wallet {
            $this->walletRepository->updateNonce($wallet, $this->signatureService->generateNonce());

            $wallet->forceFill(['is_active' => true])->save();

            return $wallet->fresh();
        });
    }

    /**
     * Generate a nonce for an address during the public MetaMask login flow.
     * The wallet must already be linked to an account.
     */
    public function generateLoginNonce(string $address): string
    {
        $address = strtolower($address);
        $wallet = $this->walletRepository->findActiveMetaMaskWalletByAddress($address);

        if (! $wallet) {
            throw ValidationException::withMessages([
                'address' => ['This address is not registered. Link your wallet first.'],
            ]);
        }

        $nonce = $this->signatureService->generateNonce();
        $this->walletRepository->updateNonce($wallet, $nonce);

        return $nonce;
    }

    /**
     * Verify MetaMask signature during public login and return the owning User.
     */
    public function verifyLoginSignature(string $address, string $signature): User
    {
        $address = strtolower($address);
        $wallet = $this->walletRepository->findActiveMetaMaskWalletByAddress($address);

        if (! $wallet) {
            throw ValidationException::withMessages([
                'address' => ['This address is not registered.'],
            ]);
        }

        $nonce = $wallet->getRawOriginal('metamask_nonce');

        if (! $nonce) {
            throw ValidationException::withMessages([
                'address' => ['No pending nonce. Request a nonce first.'],
            ]);
        }

        if (! $this->signatureService->verifySignature($nonce, $signature, $address)) {
            throw ValidationException::withMessages([
                'signature' => ['Signature verification failed.'],
            ]);
        }

        return DB::transaction(function () use ($wallet): User {
            $this->walletRepository->updateNonce($wallet, $this->signatureService->generateNonce());

            return $wallet->user()->firstOrFail();
        });
    }

    /**
     * Remove a wallet owned by the user.
     */
    public function remove(Wallet $wallet): void
    {
        $this->walletRepository->delete($wallet);
    }

    // ─── Private helpers ────────────────────────────────────────────────────────

    private function validateAddress(string $address, ChainType $chain): void
    {
        if (! preg_match($chain->addressPattern(), $address)) {
            throw ValidationException::withMessages([
                'address' => ["Invalid {$chain->label()} address format."],
            ]);
        }
    }

    private function ensureNotDuplicate(string $address, int $userId, ChainType $chain): void
    {
        if ($this->walletRepository->findByAddressAndUser(strtolower($address), $userId, $chain->value)) {
            throw ValidationException::withMessages([
                'address' => ['This wallet address is already linked to your account.'],
            ]);
        }
    }
}
