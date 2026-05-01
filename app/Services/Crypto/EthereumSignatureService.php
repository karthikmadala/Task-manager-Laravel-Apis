<?php

namespace App\Services\Crypto;

use Elliptic\EC;
use kornrunner\Keccak;
use RuntimeException;

class EthereumSignatureService
{
    /**
     * Generate a cryptographically random nonce string for MetaMask signing.
     */
    public function generateNonce(): string
    {
        return 'Sign this message to authenticate with CryptoPortfolio.\n\nNonce: ' . bin2hex(random_bytes(16));
    }

    /**
     * Verify a MetaMask personal_sign signature and return whether the
     * recovered address matches the expected address (case-insensitive).
     *
     * @throws RuntimeException if ext-gmp is missing (dev environment only)
     */
    public function verifySignature(string $nonce, string $signature, string $expectedAddress): bool
    {
        if (! extension_loaded('gmp')) {
            throw new RuntimeException(
                'ext-gmp is required for MetaMask signature verification. ' .
                'Enable it in php.ini or use the Node.js blockchain service.'
            );
        }

        $recovered = $this->recoverAddress($nonce, $signature);

        return strtolower($recovered) === strtolower($expectedAddress);
    }

    /**
     * Recover the Ethereum address that signed a MetaMask personal_sign message.
     * Implements EIP-191: "\x19Ethereum Signed Message:\n" + len + message.
     */
    public function recoverAddress(string $message, string $signature): string
    {
        $msgHash = $this->hashPersonalMessage($message);
        ['r' => $r, 's' => $s, 'v' => $v] = $this->parseSignature($signature);

        $ec = new EC('secp256k1');
        $pubKey = $ec->recoverPubKey($msgHash, ['r' => $r, 's' => $s], $v);

        // Encode uncompressed public key, strip the 04 prefix, hash, take last 20 bytes
        $pubKeyHex = $pubKey->encode('hex');
        $pubKeyBytes = hex2bin(substr($pubKeyHex, 2));
        $addrHash = Keccak::hash($pubKeyBytes, 256);

        return '0x' . substr($addrHash, -40);
    }

    /**
     * EIP-191 personal_sign prefix hash.
     */
    private function hashPersonalMessage(string $message): string
    {
        $prefix = "\x19Ethereum Signed Message:\n" . strlen($message);
        return Keccak::hash($prefix . $message, 256);
    }

    /**
     * Split a hex signature into r, s, v components.
     * Normalises v from Ethereum's 27/28 to 0/1.
     */
    private function parseSignature(string $signature): array
    {
        $sig = ltrim($signature, '0x');

        if (strlen($sig) !== 130) {
            throw new RuntimeException('Invalid signature length.');
        }

        $v = hexdec(substr($sig, 128, 2));

        // Normalise v: MetaMask uses 27/28, EC library expects 0/1
        if ($v >= 27) {
            $v -= 27;
        }

        return [
            'r' => substr($sig, 0, 64),
            's' => substr($sig, 64, 64),
            'v' => $v,
        ];
    }
}
