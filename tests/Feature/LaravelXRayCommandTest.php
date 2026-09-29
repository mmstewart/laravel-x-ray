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
