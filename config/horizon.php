<?php

use Illuminate\Support\Str;

return [
    'domain'    => null,
    'path'      => 'horizon',
    'use'       => 'default',
    'prefix'    => env('HORIZON_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_') . '_horizon:'),
    'middleware' => ['web'],
    'waits'     => [
        'redis:critical' => 3,
        'redis:default'  => 5,
        'redis:low'      => 30,
    ],
    'trim' => [
        'recent'        => 60,
        'pending'       => 60,
        'completed'     => 60,
        'recent_failed' => 10080,
        'failed'        => 10080,
        'monitored'     => 10080,
    ],
    'silenced'  => [],
    'metrics'   => [
        'trim_snapshots' => [
            'job'   => 24,
            'queue' => 24,
        ],
    ],
    'fast_termination' => false,
    'memory_limit'     => 64,
    'defaults'         => [
        'supervisor-critical' => [
            'connection' => 'redis',
            'queue'      => ['critical'],
            'balance'    => 'auto',
            'processes'  => 4,
            'tries'      => 3,
            'timeout'    => 60,
        ],
        'supervisor-default' => [
            'connection' => 'redis',
            'queue'      => ['default'],
            'balance'    => 'auto',
            'processes'  => 2,
            'tries'      => 3,
            'timeout'    => 120,
        ],
        'supervisor-low' => [
            'connection' => 'redis',
            'queue'      => ['low'],
            'balance'    => 'auto',
            'processes'  => 1,
            'tries'      => 3,
            'timeout'    => 300,
        ],
    ],
    'environments' => [
        'production' => [
            'supervisor-critical' => ['processes' => 4],
            'supervisor-default'  => ['processes' => 2],
            'supervisor-low'      => ['processes' => 1],
        ],
        'local' => [
            'supervisor-critical' => ['processes' => 2],
            'supervisor-default'  => ['processes' => 1],
            'supervisor-low'      => ['processes' => 1],
        ],
    ],
];
