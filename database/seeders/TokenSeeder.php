<?php

namespace Database\Seeders;

use App\Models\Token;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TokenSeeder extends Seeder
{
    public function run(): void
    {
        $isTestnet = app()->environment('local');

        $tokens = [
            // Native ETH
            [
                'symbol'           => 'ETH',
                'name'             => 'Ethereum',
                'chain_type'       => 'eth',
                'coingecko_id'     => 'ethereum',
                'contract_address' => null,
                'decimals'         => 18,
            ],
            // Native BNB
            [
                'symbol'           => 'BNB',
                'name'             => 'BNB',
                'chain_type'       => 'bnb',
                'coingecko_id'     => 'binancecoin',
                'contract_address' => null,
                'decimals'         => 18,
            ],
            // Native MATIC
            [
                'symbol'           => 'MATIC',
                'name'             => 'Polygon',
                'chain_type'       => 'polygon',
                'coingecko_id'     => 'matic-network',
                'contract_address' => null,
                'decimals'         => 18,
            ],
            // Native BTC (read-only)
            [
                'symbol'           => 'BTC',
                'name'             => 'Bitcoin',
                'chain_type'       => 'btc',
                'coingecko_id'     => 'bitcoin',
                'contract_address' => null,
                'decimals'         => 8,
            ],
            // USDC on ETH — Sepolia testnet: Circle's official deployment
            [
                'symbol'           => 'USDC',
                'name'             => 'USD Coin',
                'chain_type'       => 'eth',
                'coingecko_id'     => 'usd-coin',
                'contract_address' => $isTestnet
                    ? '0x1c7D4B196Cb0C7B01d743Fbc6116a902379C7238'  // Sepolia
                    : '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', // Mainnet
                'decimals'         => 6,
            ],
            // USDT on ETH — no official Sepolia deployment; skip on testnet
            ...($isTestnet ? [] : [[
                'symbol'           => 'USDT',
                'name'             => 'Tether USD',
                'chain_type'       => 'eth',
                'coingecko_id'     => 'tether',
                'contract_address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
                'decimals'         => 6,
            ]]),
            // USDT on BNB — BSC testnet: common test-USDT deployment
            [
                'symbol'           => 'USDT',
                'name'             => 'Tether USD',
                'chain_type'       => 'bnb',
                'coingecko_id'     => 'tether',
                'contract_address' => $isTestnet
                    ? '0x337610d27c682E347C9cD60BD4b3b107C9d34dDd'  // BSC Testnet
                    : '0x55d398326f99059fF775485246999027B3197955', // BSC Mainnet
                'decimals'         => 18,
            ],
            // USDC on Polygon — Amoy testnet: bridged USDC
            [
                'symbol'           => 'USDC',
                'name'             => 'USD Coin',
                'chain_type'       => 'polygon',
                'coingecko_id'     => 'usd-coin',
                'contract_address' => $isTestnet
                    ? '0x41E94Eb019C0762f9Bfcf9Fb1E58725BfB0e7582'  // Polygon Amoy
                    : '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174', // Polygon Mainnet
                'decimals'         => 6,
            ],
        ];

        foreach ($tokens as $token) {
            Token::firstOrCreate(
                ['symbol' => $token['symbol'], 'chain_type' => $token['chain_type']],
                array_merge($token, ['id' => Str::uuid()])
            );
        }
    }
}
