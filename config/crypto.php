<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Blockchain Node Microservice
    |--------------------------------------------------------------------------
    | Internal Node.js service that handles ethers.js interactions.
    | Communication is secured via HMAC-SHA256 shared secret.
    */
    'node_service' => [
        'url'    => env('BLOCKCHAIN_SERVICE_URL', 'http://localhost:3000'),
        'secret' => env('BLOCKCHAIN_SERVICE_SECRET'),
        'timeout' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | EVM RPC Endpoints (read-only fallback used by Laravel directly)
    |--------------------------------------------------------------------------
    */
    'rpc' => [
        'eth'     => env('APP_ENV') === 'local' ? env('ETH_RPC_URL_TESTNET') : env('ETH_RPC_URL'),
        'bnb'     => env('APP_ENV') === 'local' ? env('BNB_RPC_URL_TESTNET', 'https://data-seed-prebsc-1-s1.binance.org:8545') : env('BNB_RPC_URL', 'https://bsc-dataseed1.binance.org'),
        'polygon' => env('APP_ENV') === 'local' ? env('POLYGON_RPC_URL_TESTNET', 'https://polygon-amoy.drpc.org') : env('POLYGON_RPC_URL', 'https://polygon.drpc.org'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Alchemy (on-chain balance & tx data)
    |--------------------------------------------------------------------------
    */
    'alchemy' => [
        'key'     => env('ALCHEMY_API_KEY'),
        'eth'     => env('APP_ENV') === 'local' ? env('ALCHEMY_ETH_URL_TESTNET', 'https://eth-sepolia.g.alchemy.com/v2') : env('ALCHEMY_ETH_URL', 'https://eth-mainnet.g.alchemy.com/v2'),
        'polygon' => env('APP_ENV') === 'local' ? env('ALCHEMY_POLYGON_URL_TESTNET', 'https://polygon-amoy.g.alchemy.com/v2') : env('ALCHEMY_POLYGON_URL', 'https://polygon-mainnet.g.alchemy.com/v2'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CoinGecko (token prices)
    |--------------------------------------------------------------------------
    */
    'coingecko' => [
        'base_url' => env('COINGECKO_BASE_URL', 'https://api.coingecko.com/api/v3'),
        'api_key'  => env('COINGECKO_API_KEY'),
        'cache_ttl' => 300, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Block Explorer APIs (Etherscan-compatible)
    | Used for native + ERC-20 balances on each chain.
    | All three APIs share the same request/response format.
    |--------------------------------------------------------------------------
    */
    'explorer' => [
        'eth' => [
            'url' => env('APP_ENV') === 'local'
                ? env('ETHERSCAN_API_URL_TESTNET', 'https://api-sepolia.etherscan.io/api')
                : env('ETHERSCAN_API_URL', 'https://api.etherscan.io/api'),
            'key' => env('ETHERSCAN_API_KEY'),
        ],
        'bnb' => [
            'url' => env('APP_ENV') === 'local'
                ? env('BSCSCAN_API_URL_TESTNET', 'https://api-testnet.bscscan.com/api')
                : env('BSCSCAN_API_URL', 'https://api.bscscan.com/api'),
            'key' => env('BSCSCAN_API_KEY'),
        ],
        'polygon' => [
            'url' => env('APP_ENV') === 'local'
                ? env('POLYGONSCAN_API_URL_TESTNET', 'https://api-amoy.polygonscan.com/api')
                : env('POLYGONSCAN_API_URL', 'https://api.polygonscan.com/api'),
            'key' => env('POLYGONSCAN_API_KEY'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | BlockCypher (Bitcoin read-only)
    |--------------------------------------------------------------------------
    */
    'blockcypher' => [
        'token'    => env('BLOCKCYPHER_TOKEN'),
        'base_url' => env('BLOCKCYPHER_BASE_URL', 'https://api.blockcypher.com/v1'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported chains and their metadata
    |--------------------------------------------------------------------------
    */
    'chains' => [
        'eth' => [
            'name'       => 'Ethereum',
            'chain_id'   => 1,
            'symbol'     => 'ETH',
            'decimals'   => 18,
            'read_only'  => false,
        ],
        'bnb' => [
            'name'       => 'BNB Chain',
            'chain_id'   => 56,
            'symbol'     => 'BNB',
            'decimals'   => 18,
            'read_only'  => false,
        ],
        'polygon' => [
            'name'       => 'Polygon',
            'chain_id'   => 137,
            'symbol'     => 'MATIC',
            'decimals'   => 18,
            'read_only'  => false,
        ],
        'btc' => [
            'name'       => 'Bitcoin',
            'chain_id'   => null,
            'symbol'     => 'BTC',
            'decimals'   => 8,
            'read_only'  => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Balance sync schedule
    |--------------------------------------------------------------------------
    */
    'sync' => [
        'balance_interval_minutes' => 5,
        'price_interval_minutes'   => 5,
        'retry_attempts'           => 3,
        'retry_delay_seconds'      => 10,
    ],

];
