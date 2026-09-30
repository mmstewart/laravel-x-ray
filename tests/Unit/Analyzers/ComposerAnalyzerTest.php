<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mmstewart\LaravelXRay\Analyzers\ComposerAnalyzer;

beforeEach(function () {
    $this->composerPath = base_path('composer.json');
    $this->backupPath = base_path('composer.json.test-backup');

    copy($this->composerPath, $this->backupPath);
});

afterEach(function () {
    rename($this->backupPath, $this->composerPath);
});

function fakeLaravelSkeleton(array $composer): void
{
    Http::fake([
        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([
            'content' => base64_encode(json_encode($composer)),
        ]),
    ]);
}

it('does not report a compatible php version', function () {
    file_put_contents(
        base_path('composer.json'),
        json_encode([
            'require' => [
                'php' => '^8.3',
            ],
        ])
    );

    $skeleton = [
        [
            'filename' => 'composer.json',
            'patch' => '+    "php": "^8.3",',
        ],
    ];

    fakeLaravelSkeleton([
        'require' => [
            'php' => '^8.3',
        ],
    ]);

    $results = (new ComposerAnalyzer($skeleton, '13.x'))->analyze();

    expect($results)
        ->not->toContain(
            fn ($issue) => $issue['key'] === 'php'
        );
});

it('reports an incompatible php version', function () {
    file_put_contents(
        base_path('composer.json'),
        json_encode([
            'require' => [
                'php' => '^9.0',
            ],
        ])
    );

    $skeleton = [
        [
            'filename' => 'composer.json',
            'patch' => '+    "php": "^99.0",',
        ],
    ];

    fakeLaravelSkeleton([
        'require' => [
            'php' => '^8.3',
        ],
    ]);

    $results = (new ComposerAnalyzer($skeleton, '13.x'))->analyze();

    $issue = collect($results)->firstWhere('key', 'php');

    expect($issue)->not->toBeNull()
        ->and($issue['type'])->toBe('composer')
        ->and($issue['severity'])->toBe('error')
        ->and($issue['message'])->toContain('PHP');
});

it('reports a dependency constraint that differs from the skeleton', function () {
    file_put_contents(
        base_path('composer.json'),
        json_encode([
            'require' => [
                'php' => '^8.3',
                'laravel/tinker' => '^2.9',
            ],
        ])
    );

    fakeLaravelSkeleton([
        'require' => [
            'php' => '^8.3',
            'laravel/tinker' => '^3.0',
        ],
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect($results)->toContain([
        'type' => 'composer',
        'severity' => 'warning',
        'message' => 'laravel/tinker (^2.9) requires adjustment for Laravel 13.x compatibility. Recommended constraint: ^3.0.',
        'key' => 'laravel/tinker',
    ]);
});

it('does not report a dependency when the constraint matches the skeleton', function () {
    file_put_contents(
        base_path('composer.json'),
        json_encode([
            'require' => [
                'php' => '^8.3',
                'laravel/tinker' => '^3.0',
            ],
        ])
    );

    fakeLaravelSkeleton([
        'require' => [
            'php' => '^8.3',
            'laravel/tinker' => '^3.0',
        ],
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect($results)
        ->not->toContain(
            fn ($issue) => $issue['key'] === 'laravel/tinker'
        );
});

it('reports a development dependency that needs adjustment', function () {
    file_put_contents(
        base_path('composer.json'),
        json_encode([
            'require' => [
                'php' => '^8.3',
            ],
            'require-dev' => [
                'phpunit/phpunit' => '^11.0.1',
            ],
        ])
    );

    fakeLaravelSkeleton([
        'require' => [
            'php' => '^8.3',
        ],
        'require-dev' => [
            'phpunit/phpunit' => '^12.5.12',
        ],
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect($results)->toContain([
        'type' => 'composer',
        'severity' => 'warning',
        'message' => 'phpunit/phpunit (^11.0.1) requires adjustment for Laravel 13.x compatibility. Recommended constraint: ^12.5.12.',
        'key' => 'phpunit/phpunit',
    ]);
});

it('does not report the x-ray package itself', function () {
    file_put_contents(
        base_path('composer.json'),
        json_encode([
            'require' => [
                'php' => '^8.3',
                'mmstewart/laravel-x-ray' => '@dev',
            ],
        ])
    );

    fakeLaravelSkeleton([
        'require' => [
            'php' => '^8.3',
        ],
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect($results)
        ->not->toContain(
            fn ($issue) => $issue['key'] === 'mmstewart/laravel-x-ray'
        );
});

it('does not report composer semver as a compatibility issue', function () {
    file_put_contents(
        base_path('composer.json'),
        json_encode([
            'require' => [
                'php' => '^8.3',
                'composer/semver' => '^3.0',
            ],
        ])
    );

    fakeLaravelSkeleton([
        'require' => [
            'php' => '^8.3',
        ],
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect($results)
        ->not->toContain(
            fn ($issue) => $issue['key'] === 'composer/semver'
        );
});

it('ignores dev versions from packagist', function () {
    file_put_contents(
        base_path('composer.json'),
        json_encode([
            'require' => [
                'php' => '^8.3',
                'example/package' => '^1.0',
            ],
        ])
    );

    Http::fake([
        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([
            'content' => base64_encode(json_encode([
                'require' => [
                    'php' => '^8.3',
                ],
            ])),
        ]),
        'packagist.org/packages/example/package.json' => Http::response([
            'package' => [
                'versions' => [
                    '2.0.0-dev' => [
                        'require' => [
                            'laravel/framework' => '^13.0',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    $issue = collect($results)->firstWhere('key', 'example/package');

    expect($issue)->not->toBeNull()
        ->and($issue['severity'])->toBe('info');
});

it('reports when a package cannot be checked for compatibility', function () {
    file_put_contents(
        base_path('composer.json'),
        json_encode([
            'require' => [
                'php' => '^8.3',
                'example/package' => '^1.0',
            ],
        ])
    );

    Http::fake([
        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([
            'content' => base64_encode(json_encode([
                'require' => [
                    'php' => '^8.3',
                ],
            ])),
        ]),
        'packagist.org/packages/example/package.json' => Http::response([], 500),
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect($results)->toContain([
        'type' => 'composer',
        'severity' => 'info',
        'message' => 'example/package could not be checked for Laravel 13.x compatibility.',
        'key' => 'example/package',
    ]);
});

it('fails when the laravel skeleton cannot be fetched from github', function () {
    Cache::forget('xray:skeleton:composer:13.x');

    Http::fake([
        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([], 500),
    ]);

    expect(fn () => (new ComposerAnalyzer([], '13.x'))->analyze())
        ->toThrow(RuntimeException::class);
});
