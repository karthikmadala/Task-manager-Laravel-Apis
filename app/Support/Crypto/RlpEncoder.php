<?php

namespace App\Support\Crypto;

/**
 * Recursive Length Prefix (RLP) encoder for Ethereum transaction serialization.
 * Implements the encoding spec: https://ethereum.org/en/developers/docs/data-structures-and-encoding/rlp/
 */
class RlpEncoder
{
    /**
     * RLP-encode a value (string/binary or array of values).
     */
    public static function encode(mixed $input): string
    {
        if (is_array($input)) {
            $output = '';
            foreach ($input as $item) {
                $output .= self::encode($item);
            }

            return self::encodeLength(strlen($output), 0xC0) . $output;
        }

        $input = (string) $input;

        if ($input === '' || $input === "\x00") {
            // Empty string
            if ($input === '') {
                return "\x80";
            }
        }

        // Single byte with value 0x00–0x7F encodes as itself
        if (strlen($input) === 1 && ord($input[0]) < 0x80) {
            return $input;
        }

        return self::encodeLength(strlen($input), 0x80) . $input;
    }

    /**
     * Encode an integer as a big-endian binary string (no leading zero bytes),
     * suitable for use as an RLP input field.
     */
    public static function intToBytes(int|string $value): string
    {
        if ($value === 0 || $value === '0') {
            return '';
        }

        $hex = is_int($value) ? dechex($value) : self::decimalToHex((string) $value);

        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        return hex2bin($hex);
    }

    /**
     * Encode a hex string (with or without 0x prefix) as raw bytes for RLP.
     */
    public static function hexToBytes(string $hex): string
    {
        $hex = ltrim($hex, '0x');

        if ($hex === '' || $hex === '0') {
            return '';
        }

        if (strlen($hex) % 2 !== 0) {
            $hex = '0' . $hex;
        }

        return hex2bin($hex);
    }

    // ─── Private ─────────────────────────────────────────────────────────────

    private static function encodeLength(int $length, int $offset): string
    {
        if ($length < 56) {
            return chr($length + $offset);
        }

        $hexLength = dechex($length);
        if (strlen($hexLength) % 2 !== 0) {
            $hexLength = '0' . $hexLength;
        }

        $binaryLength = hex2bin($hexLength);
        $lengthOfLength = strlen($binaryLength);

        return chr($offset + 55 + $lengthOfLength) . $binaryLength;
    }

    private static function decimalToHex(string $decimal): string
    {
        if ($decimal === '0') {
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
