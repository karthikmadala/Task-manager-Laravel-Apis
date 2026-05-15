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
        $address = strtolower($data['address']);

        $this->validateAddress($address, $chain);

        $existing = $this->findExistingWallet($address, $user->id, $chain);

        if ($existing && ! $existing->trashed()) {
            throw ValidationException::withMessages([
                'address' => ['This wallet address is already linked to your account.'],
            ]);
        }

        if ($existing) {
            return $this->walletRepository->restore($existing, [
                'chain_type'  => $chain->value,
                'wallet_type' => WalletType::EXTERNAL->value,
                'address'     => $address,
                'label'       => $data['label'] ?? null,
                'metamask_nonce' => null,
                'is_active'   => true,
            ]);
        }

        return $this->walletRepository->create([
            'user_id'     => $user->id,
            'chain_type'  => $chain->value,
            'wallet_type' => WalletType::EXTERNAL->value,
            'address'     => $address,
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
        $existingWallet = $this->walletRepository->findByAddressAndUserIncludingTrashed($address, $user->id);

        if ($existingWallet && ! $existingWallet->trashed() && $existingWallet->is_active) {
            throw ValidationException::withMessages([
                'address' => ['This wallet address is already linked to your account.'],
            ]);
        }

        $nonce = $this->signatureService->generateNonce();

        $wallet = $existingWallet
            ? $this->walletRepository->restore($existingWallet, [
                'chain_type'  => ChainType::ETH->value,
                'wallet_type' => WalletType::METAMASK->value,
                'address'     => $address,
                'is_active'   => false,
            ])
            : $this->walletRepository->create([
                'user_id'     => $user->id,
                'chain_type'  => ChainType::ETH->value,
                'wallet_type' => WalletType::METAMASK->value,
                'address'     => $address,
                'is_active'   => false, // activated after signature verified
            ]);

        $this->walletRepository->updateNonce($wallet, $nonce, now()->addMinutes(10));

        return $nonce;
    }

    /**
     * Verify a MetaMask signature and activate the wallet link.
     */
    public function verifyAndLink(User $user, string $address, string $signature): Wallet
    {
        $address = strtolower($address);
        $wallet = $this->walletRepository->findByAddressAndUser($address, $user->id);

        if (! $wallet || ! $wallet->getRawOriginal('metamask_nonce')) {
            throw ValidationException::withMessages([
                'address' => ['No pending nonce found. Request a new nonce first.'],
            ]);
        }

        // getRawOriginal bypasses the $hidden guard to access the nonce
        $nonce = $wallet->getRawOriginal('metamask_nonce');

        $nonceExpiresAt = $wallet->getRawOriginal('metamask_nonce_expires_at');
        if ($nonceExpiresAt && \Carbon\Carbon::parse($nonceExpiresAt)->isPast()) {
            throw ValidationException::withMessages([
                'address' => ['Nonce has expired. Please request a new nonce.'],
            ]);
        }

        if (! $this->signatureService->verifySignature($nonce, $signature, $address)) {
            throw ValidationException::withMessages([
                'signature' => ['Signature verification failed.'],
            ]);
        }

        return DB::transaction(function () use ($wallet): Wallet {
            $this->walletRepository->updateNonce($wallet, $this->signatureService->generateNonce(), now()->addMinutes(10));

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
        $this->walletRepository->updateNonce($wallet, $nonce, now()->addMinutes(10));

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

        $nonceExpiresAt = $wallet->getRawOriginal('metamask_nonce_expires_at');
        if ($nonceExpiresAt && \Carbon\Carbon::parse($nonceExpiresAt)->isPast()) {
            throw ValidationException::withMessages([
                'address' => ['Nonce has expired. Please request a new nonce.'],
            ]);
        }

        if (! $this->signatureService->verifySignature($nonce, $signature, $address)) {
            throw ValidationException::withMessages([
                'signature' => ['Signature verification failed.'],
            ]);
        }

        return DB::transaction(function () use ($wallet): User {
            $this->walletRepository->updateNonce($wallet, $this->signatureService->generateNonce(), now()->addMinutes(10));

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

    private function findExistingWallet(string $address, int $userId, ChainType $chain): ?Wallet
    {
        return $chain->isEvm()
            ? $this->walletRepository->findByAddressAndUserIncludingTrashed(strtolower($address), $userId)
            : $this->walletRepository->findByAddressAndUserIncludingTrashed(strtolower($address), $userId, $chain->value);
    }
}
