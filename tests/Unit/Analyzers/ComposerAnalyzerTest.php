<?php

use Illuminate\Http\Client\ConnectionException;
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
        'message' => 'laravel/tinker (^2.9) is not compatible with Laravel 13.x. Recommended constraint: ^3.0.',
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

it('does not report a dependency when its constraint is compatible with the skeleton', function () {
    file_put_contents(
        base_path('composer.json'),
        json_encode([
            'require' => [
                'php' => '^8.3',
                'laravel/tinker' => '^3.1',
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

    expect(collect($results)->firstWhere('key', 'laravel/tinker'))->toBeNull();
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
        'message' => 'phpunit/phpunit (^11.0.1) is not compatible with Laravel 13.x. Recommended constraint: ^12.5.12.',
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
    Http::fake([
        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([], 500),
    ]);

    expect(fn () => (new ComposerAnalyzer([], '13.x'))->analyze())->toThrow(RuntimeException::class);
});

it('reports a dependency when its constraint is broader than the skeleton', function () {
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
            'laravel/tinker' => '^3.1',
        ],
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect(collect($results)->firstWhere('key', 'laravel/tinker'))->not->toBeNull();
});

it('recommends the highest compatible stable package version', function () {
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
                    '8.3.0' => [
                        'require' => [
                            'laravel/framework' => '^13.0',
                        ],
                    ],
                    '8.5.0-beta.1' => [
                        'require' => [
                            'laravel/framework' => '^13.0',
                        ],
                    ],
                    '8.4.0' => [
                        'require' => [
                            'laravel/framework' => '^13.0',
                        ],
                    ],
                    '8.3.1' => [
                        'require' => [
                            'laravel/framework' => '^13.0',
                        ],
                    ],
                    '8.2.0' => [
                        'require' => [
                            'laravel/framework' => '^12.0',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect($results)->toContain([
        'type' => 'composer',
        'severity' => 'warning',
        'message' => 'example/package (^1.0) is not compatible with Laravel 13.x. Recommended constraint: ^8.4.',
        'key' => 'example/package',
    ]);
});

it('does not report a third-party dependency when the installed constraint allows the recommended version', function () {
    file_put_contents(
        base_path('composer.json'),
        json_encode([
            'require' => [
                'php' => '^8.3',
                'example/package' => '^8.3',
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
                    '8.4.0' => [
                        'require' => [
                            'laravel/framework' => '^13.0',
                        ],
                    ],
                    '8.3.0' => [
                        'require' => [
                            'laravel/framework' => '^13.0',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect(collect($results)->firstWhere('key', 'example/package'))->toBeNull();
});

it('reports an info message when a third-party dependency has no Laravel requirement', function () {
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
                    '1.2.0' => [
                        'require' => [
                            'php' => '^8.3',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect($results)->toContain([
        'type' => 'composer',
        'severity' => 'info',
        'message' => 'example/package could not be checked for Laravel 13.x compatibility.',
        'key' => 'example/package',
    ]);
});

it('reports an info message when a third-party dependency cannot be checked', function () {
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

it('checks all Laravel-related requirements before recommending a package version', function () {
    config(['x-ray.cache.enabled' => false]);

    file_put_contents(
        base_path('composer.json'),
        json_encode([
            'require' => [
                'php' => '^8.3',
                'example/package' => '^0.9',
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
                    '2.0.0' => [
                        'require' => [
                            'laravel/framework' => '^13.0',
                            'illuminate/support' => '^12.0',
                        ],
                    ],
                    '1.5.0' => [
                        'require' => [
                            'laravel/framework' => '^13.0',
                            'illuminate/support' => '^13.0',
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect($results)->toContain([
        'type' => 'composer',
        'severity' => 'warning',
        'message' => 'example/package (^0.9) is not compatible with Laravel 13.x. Recommended constraint: ^1.5.',
        'key' => 'example/package',
    ]);
});

it('correctly compares Composer version constraints', function (
    string $installed,
    string $recommended,
    bool $compatible
) {
    file_put_contents(
        base_path('composer.json'),
        json_encode([
            'require' => [
                'php' => '^8.3',
                'example/package' => $installed,
            ],
        ])
    );

    config(['x-ray.cache.enabled' => false]);

    Http::fake([
        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([
            'content' => base64_encode(json_encode([
                'require' => [
                    'php' => '^8.3',
                    'example/package' => $recommended,
                ],
            ])),
        ]),
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    $hasWarning = collect($results)->contains(
        fn ($result) => $result['key'] === 'example/package'
            && $result['severity'] === 'warning'
    );

    expect($hasWarning)->toBe(! $compatible);
})->with([
    'installed range is inside recommended range' => [
        '^6.18',
        '^6.0',
        true,
    ],
    'installed range is broader than recommended range' => [
        '^6.0',
        '^6.18',
        false,
    ],
    'installed range is an older major version' => [
        '^5.7',
        '^6.18',
        false,
    ],
    'OR constraint includes an unsupported range' => [
        '^5.0 || ^6.0',
        '^6.0',
        false,
    ],
]);

it('throws an exception when GitHub fails to fetch the Laravel skeleton composer file', function () {
    config(['x-ray.cache.enabled' => false]);

    Http::fake([
        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response(
            ['message' => 'Not Found'],
            404
        ),
    ]);

    expect(fn () => (new ComposerAnalyzer([], '13.x'))->analyze())
        ->toThrow(RuntimeException::class);
});

it('throws an exception when GitHub returns invalid Laravel skeleton composer content', function () {
    config(['x-ray.cache.enabled' => false]);

    Http::fake([
        'api.github.com/repos/laravel/laravel/contents/composer.json*' => Http::response([
            'content' => base64_encode('{invalid json'),
        ]),
    ]);

    expect(fn () => (new ComposerAnalyzer([], '13.x'))->analyze())
        ->toThrow(RuntimeException::class);
});

it('handles unsuccessful Packagist responses', function () {
    config(['x-ray.cache.enabled' => false]);

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
                    'example/package' => '^2.0',
                ],
            ])),
        ]),
        'packagist.org/packages/example/package.json' => Http::response([], 500),
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect(collect($results)->contains(
        fn ($result) => $result['key'] === 'example/package'
            && $result['severity'] === 'warning'
    ))->toBeTrue();
});

it('handles Packagist responses without version data', function () {
    config(['x-ray.cache.enabled' => false]);

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
                    'example/package' => '^2.0',
                ],
            ])),
        ]),
        'packagist.org/packages/example/package.json' => Http::response([
            'package' => [],
        ]),
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect(collect($results)->contains(
        fn ($result) => $result['key'] === 'example/package'
            && $result['severity'] === 'warning'
    ))->toBeTrue();
});

it('handles Packagist connection failures', function () {
    config(['x-ray.cache.enabled' => false]);

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
                    'example/package' => '^2.0',
                ],
            ])),
        ]),
        'packagist.org/packages/example/package.json' => fn () => throw new ConnectionException(
            'Connection failed.'
        ),
    ]);

    $results = (new ComposerAnalyzer([], '13.x'))->analyze();

    expect(collect($results)->contains(
        fn ($result) => $result['key'] === 'example/package'
            && $result['severity'] === 'warning'
    ))->toBeTrue();
});
