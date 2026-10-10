<?php

/*
|--------------------------------------------------------------------------
| Slipway: CI pipeline for the filament-notifier package
|--------------------------------------------------------------------------
|
| .github/workflows/slipway.yml is GENERATED from this file; never edit it by hand.
| After changing this file run:
|
|   composer slipway:compile      (vendor/bin/testbench slipway:compile)
|
| and commit both files. The `slipway-guard` job in CI fails if they disagree.
|
*/

return [

    'name' => 'CI',

    'drivers' => [
        'github' => [
            'enabled' => true,
            // Resolved with `vendor/bin/testbench slipway:pin`.
            'options' => [
                'pins' => [
                    'actions/cache' => '0057852bfaa89a56745cba8c7296529d2fc39830', // v4
                    'actions/checkout' => '11d5960a326750d5838078e36cf38b85af677262', // v4
                    'shivammathur/setup-php' => 'eb7c497e18156a6bbabfef1d3a82760b9eda3962', // v2
                ],
            ],
        ],
    ],

    'strict' => false,
    'guard' => true,

    // Libraries do not commit composer.lock, so key the cache on composer.json and audit what was installed.
    'lockfile' => false,

    // Run Slipway's commands (drift check, secret scan) through Testbench.
    'artisan' => 'vendor/bin/testbench',

    // Every PHP version the package supports (Laravel 13 requires 8.3+).
    'php' => ['8.3', '8.4', '8.5'],
    'node' => null,

    'triggers' => [
        'push' => ['main'],
        'tags' => [],
        'pull_request' => true,
        'schedule' => ['0 3 * * 1'],   // weekly: catches breakage from new dependency releases
        'manual' => true,
        'paths_ignore' => [],
    ],

    'concurrency' => ['cancel_superseded' => true],
    'timeouts' => ['default' => 15],
    'cache' => true,
    'env' => [],

    'stages' => [
        'quality' => ['pint', 'composer-validate', 'composer-audit', 'secret-scan'],

        'test' => [
            'matrix' => true,
            // The suite is PHPUnit-based and runs on Testbench, so skip the Laravel app environment preparation.
            'steps' => ['phpunit' => ['prepare_env' => false]],
        ],
    ],

    'environments' => [],
    'policies' => [],
    'secrets' => [],

];
