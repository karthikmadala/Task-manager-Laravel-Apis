<?php

namespace App\Services\Crypto;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

/**
 * Bitcoin balance fetching via BlockCypher API (read-only).
 */
class BlockCypherService
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client([
            'base_uri' => config('crypto.blockcypher.base_url'),
            'timeout'  => 10,
        ]);
    }

    /**
     * Fetch confirmed + unconfirmed balance for a Bitcoin address.
     * Returns balance in satoshis as a decimal string.
     *
     * @return array{ balance: string, unconfirmed_balance: string, final_balance: string }
     */
    public function getBalance(string $address): array
    {
        try {
            $query = [];

            if ($token = config('crypto.blockcypher.token')) {
                $query['token'] = $token;
            }

            $response = $this->http->get("/btc/main/addrs/{$address}/balance", [
                'query' => $query,
            ]);

            $data = json_decode((string) $response->getBody(), true);

            return [
                'balance'             => (string) ($data['balance'] ?? 0),
                'unconfirmed_balance' => (string) ($data['unconfirmed_balance'] ?? 0),
                'final_balance'       => (string) ($data['final_balance'] ?? 0),
            ];
        } catch (GuzzleException $e) {
            Log::error('BlockCypher balance fetch failed', [
                'address' => $address,
                'error'   => $e->getMessage(),
            ]);

            return ['balance' => '0', 'unconfirmed_balance' => '0', 'final_balance' => '0'];
        }
    }

    /**
     * Convert satoshis (decimal string) to BTC string.
     */
    public function satoshiToBtc(string $satoshis): string
    {
        if ($satoshis === '0') {
            return '0';
        }

        return bcdiv($satoshis, '100000000', 8);
    }
}
