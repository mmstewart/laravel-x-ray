<?php

use Illuminate\Support\Facades\Http;

it('runs a laravel upgrade scan successfully', function () {
    config([
        'x-ray.minimum_severity' => 'info',
        'x-ray.cache.enabled' => false,
    ]);

    Http::fake([
        'api.github.com/repos/laravel/laravel' => Http::response([
            'default_branch' => '13.x',
        ]),

        'api.github.com/repos/laravel/laravel/compare/*' => Http::response([
            'files' => [],
        ]),

        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([
            'content' => base64_encode(json_encode([
                'require' => [
                    'php' => '^8.3',
                    'laravel/framework' => '^13.0',
                ],
                'require-dev' => [],
            ])),
        ]),
    ]);

    $this->artisan('laravel-x-ray', [
        'from' => '11.x',
        'to' => '13.x',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Comparing 11.x → 13.x');
});

it('rejects a downgrade', function () {
    $this->artisan('laravel-x-ray', [
        'from' => '13.x',
        'to' => '11.x',
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain('Invalid upgrade path: 13.x → 11.x');
});

it('handles comparing the same laravel version', function () {
    $this->artisan('laravel-x-ray', [
        'from' => '11.x',
        'to' => '11.x',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain(
            'No newer Laravel skeleton branch is available.'
        );
});

it('uses the installed version when from is omitted', function () {
    config([
        'x-ray.minimum_severity' => 'info',
        'x-ray.cache.enabled' => false,
    ]);

    Http::fake([
        'api.github.com/repos/laravel/laravel' => Http::response([
            'default_branch' => '13.x',
        ]),

        'api.github.com/repos/laravel/laravel/compare/*' => Http::response([
            'files' => [],
        ]),

        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([
            'content' => base64_encode(json_encode([
                'require' => [
                    'php' => '^8.3',
                    'laravel/framework' => '^13.0',
                ],
            ])),
        ]),
    ]);

    $installedBranch = app()->version();
    $installedBranch = explode('.', $installedBranch)[0].'.x';

    $this->artisan('laravel-x-ray')
        ->assertSuccessful()
        ->expectsOutputToContain("Comparing {$installedBranch} → 13.x");
});

it('outputs a JSON report when the json format is requested', function () {
    config([
        'x-ray.minimum_severity' => 'info',
        'x-ray.cache.enabled' => false,
    ]);

    Http::fake([
        'api.github.com/repos/laravel/laravel' => Http::response([
            'default_branch' => '13.x',
        ]),

        'api.github.com/repos/laravel/laravel/compare/*' => Http::response([
            'files' => [],
        ]),

        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([
            'content' => base64_encode(json_encode([
                'require' => [
                    'php' => '^8.3',
                    'laravel/framework' => '^13.0',
                ],
                'require-dev' => [],
            ])),
        ]),
    ]);

    $this->artisan('laravel-x-ray', [
        'from' => '11.x',
        'to' => '13.x',
        '--format' => 'json',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('"summary":')
        ->doesntExpectOutputToContain('Laravel X-Ray Upgrade Report')
        ->doesntExpectOutputToContain('Comparing 11.x → 13.x');
});

it('rejects an unsupported report format', function () {
    $this->artisan('laravel-x-ray', [
        'from' => '11.x',
        'to' => '13.x',
        '--format' => 'xml',
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain(
            'Invalid format. Supported formats: table, json.'
        );
});

it('fails when the warning threshold is met', function () {
    config([
        'x-ray.minimum_severity' => 'info',
        'x-ray.cache.enabled' => false,
    ]);

    Http::fake([
        'api.github.com/repos/laravel/laravel' => Http::response([
            'default_branch' => '13.x',
        ]),

        'api.github.com/repos/laravel/laravel/compare/*' => Http::response([
            'files' => [
                [
                    'filename' => 'config/services.php',
                    'status' => 'modified',
                    'patch' => "+'token' => env('POSTMARK_TOKEN'),\n+'key' => env('POSTMARK_API_KEY'),",
                ],
            ],
        ]),

        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([
            'content' => base64_encode(json_encode([
                'require' => [
                    'php' => '^8.3',
                    'laravel/framework' => '^13.0',
                ],
                'require-dev' => [],
            ])),
        ]),
    ]);

    $this->artisan('laravel-x-ray', [
        'from' => '11.x',
        'to' => '13.x',
        '--fail-on' => 'warning',
    ])->assertExitCode(1);
});

it('succeeds when the error threshold is not met', function () {
    config([
        'x-ray.minimum_severity' => 'info',
        'x-ray.cache.enabled' => false,
    ]);

    Http::fake([
        'api.github.com/repos/laravel/laravel' => Http::response([
            'default_branch' => '13.x',
        ]),

        'api.github.com/repos/laravel/laravel/compare/*' => Http::response([
            'files' => [
                [
                    'filename' => 'config/services.php',
                    'status' => 'modified',
                    'patch' => "+'token' => env('POSTMARK_TOKEN'),\n+'key' => env('POSTMARK_API_KEY'),",
                ],
            ],
        ]),

        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([
            'content' => base64_encode(json_encode([
                'require' => [
                    'php' => '^8.3',
                    'laravel/framework' => '^13.0',
                ],
                'require-dev' => [],
            ])),
        ]),
    ]);

    $this->artisan('laravel-x-ray', [
        'from' => '11.x',
        'to' => '13.x',
        '--fail-on' => 'error',
    ])->assertSuccessful();
});

it('rejects an unsupported failure threshold', function () {
    $this->artisan('laravel-x-ray', [
        'from' => '11.x',
        'to' => '13.x',
        '--fail-on' => 'critical',
    ])
        ->assertExitCode(1)
        ->expectsOutputToContain(
            'Invalid failure threshold. Supported values: error, warning, info.'
        );
});

it('fails when the info threshold is met', function () {
    config([
        'x-ray.minimum_severity' => 'info',
        'x-ray.cache.enabled' => false,
    ]);

    Http::fake([
        'api.github.com/repos/laravel/laravel' => Http::response([
            'default_branch' => '13.x',
        ]),
        'api.github.com/repos/laravel/laravel/compare/*' => Http::response([
            'files' => [
                [
                    'filename' => 'config/legacy.php',
                    'status' => 'removed',
                ],
            ],
        ]),
        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([
            'content' => base64_encode(json_encode([
                'require' => [
                    'php' => '^8.3',
                    'laravel/framework' => '^13.0',
                ],
                'require-dev' => [],
            ])),
        ]),
    ]);

    $this->artisan('laravel-x-ray', [
        'from' => '11.x',
        'to' => '13.x',
        '--fail-on' => 'info',
    ])->expectsOutputToContain('SUCCESS: No findings meet the configured failure threshold');
});

it('succeeds by default when findings are present', function () {
    config([
        'x-ray.minimum_severity' => 'info',
        'x-ray.cache.enabled' => false,
    ]);

    Http::fake([
        '*api.github.com/repos/laravel/laravel' => Http::response([
            'default_branch' => '13.x',
        ]),
        '*api.github.com/repos/laravel/laravel/compare/*' => Http::response([
            'files' => [
                [
                    'filename' => 'config/legacy.php',
                    'status' => 'removed',
                ],
            ],
        ]),
        '*api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([
            'content' => base64_encode(json_encode([
                'require' => [
                    'php' => '^8.3',
                    'laravel/framework' => '^13.0',
                ],
                'require-dev' => [],
            ])),
        ]),
    ]);

    $this->artisan('laravel-x-ray', [
        'from' => '11.x',
        'to' => '13.x',
    ])->assertSuccessful();
});
