<?php

namespace App\Support\Crypto;

use kornrunner\Keccak;

/**
 * Solidity ABI encoder for EVM smart contract call data.
 * Supports the subset of types used by staking and ICO contracts.
 */
class AbiEncoder
{
    /**
     * Compute the 4-byte function selector from a Solidity signature.
     * e.g. "stake(uint256,uint256)" → first 4 bytes of keccak256.
     */
    public static function functionSelector(string $signature): string
    {
        return substr(hex2bin(Keccak::hash($signature, 256)), 0, 4);
    }

    /**
     * Encode a call to a function with fixed-size parameters only.
     * Returns raw bytes (prepend with 0x and convert to hex for API calls).
     *
     * @param  string  $signature  Solidity function signature e.g. "stake(uint256,uint256)"
     * @param  array   $params     Positional param values (uint256 as string|int, address as hex string)
     * @param  string[]  $types    Corresponding types: 'uint256', 'address', 'uint8', 'bytes32'
     */
    public static function encodeCall(string $signature, array $params, array $types): string
    {
        $selector = self::functionSelector($signature);
        $encoded = $selector;

        foreach ($params as $i => $value) {
            $encoded .= self::encodeParam($value, $types[$i] ?? 'uint256');
        }

        return $encoded;
    }

    /**
     * Encode a single parameter to 32 bytes.
     */
    public static function encodeParam(mixed $value, string $type): string
    {
        return match (true) {
            $type === 'address' => self::encodeAddress((string) $value),
            str_starts_with($type, 'uint') => self::encodeUint($value),
            str_starts_with($type, 'int') => self::encodeUint($value),
            $type === 'bytes32' => self::encodeBytes32((string) $value),
            $type === 'bool' => self::encodeBool((bool) $value),
            default => self::encodeUint($value),
        };
    }

    /**
     * ABI-encode an address (20 bytes left-padded to 32).
     */
    public static function encodeAddress(string $address): string
    {
        $hex = ltrim(strtolower($address), '0x');

        return hex2bin(str_pad($hex, 64, '0', STR_PAD_LEFT));
    }

    /**
     * ABI-encode a uint256/uint8 value.
     */
    public static function encodeUint(int|string $value): string
    {
        $hex = is_int($value) ? dechex($value) : self::decimalToHex((string) $value);

        return hex2bin(str_pad($hex, 64, '0', STR_PAD_LEFT));
    }

    /**
     * ABI-encode a bytes32 value (already hex, right-padded to 32 bytes).
     */
    public static function encodeBytes32(string $hexValue): string
    {
        $hex = ltrim($hexValue, '0x');

        return hex2bin(str_pad($hex, 64, '0', STR_PAD_RIGHT));
    }

    /**
     * ABI-encode a bool.
     */
    public static function encodeBool(bool $value): string
    {
        return self::encodeUint($value ? 1 : 0);
    }

    /**
     * Encode a Solidity tuple (struct) as concatenated encoded fields.
     * For ICO buyToken Sign struct: (uint8 v, bytes32 r, bytes32 s, uint256 nonce)
     *
     * @param  array  $fields  ['v' => ..., 'r' => ..., 's' => ..., 'nonce' => ...]
     */
    public static function encodeSignStruct(array $fields): string
    {
        return self::encodeUint($fields['v'])
            . self::encodeBytes32($fields['r'])
            . self::encodeBytes32($fields['s'])
            . self::encodeUint($fields['nonce']);
    }

    /**
     * Encode a solidity packed hash (equivalent to abi.encodePacked).
     * Used for ICO signature generation.
     *
     * @param  array  $types   ['uint256', 'address', 'address', 'uint256', 'uint256']
     * @param  array  $values  Corresponding values
     */
    public static function solidityPackedKeccak256(array $types, array $values): string
    {
        $packed = '';

        foreach ($types as $i => $type) {
            $value = $values[$i];

            if ($type === 'address') {
                // 20 bytes
                $hex = ltrim(strtolower((string) $value), '0x');
                $packed .= hex2bin(str_pad($hex, 40, '0', STR_PAD_LEFT));
            } elseif (str_starts_with($type, 'uint')) {
                // Get bit size, default 256
                $bits = (int) (substr($type, 4) ?: '256');
                $bytes = $bits / 8;
                $hex = self::decimalToHex((string) $value);
                if (strlen($hex) % 2 !== 0) {
                    $hex = '0' . $hex;
                }
                $packed .= hex2bin(str_pad($hex, $bytes * 2, '0', STR_PAD_LEFT));
            } elseif ($type === 'bytes32') {
                $hex = ltrim((string) $value, '0x');
                $packed .= hex2bin(str_pad($hex, 64, '0', STR_PAD_LEFT));
            }
        }

        return Keccak::hash($packed, 256);
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private static function decimalToHex(string $decimal): string
    {
        if ($decimal === '0' || $decimal === '') {
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
