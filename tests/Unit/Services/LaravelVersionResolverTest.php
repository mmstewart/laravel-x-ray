<?php

use Illuminate\Support\Facades\Http;
use Mmstewart\LaravelXRay\Services\LaravelVersionResolver;

it('returns the installed laravel version', function () {
    $resolver = new LaravelVersionResolver;

    expect($resolver->version())->toBe(app()->version());
});

it('normalizes an x version into a semver version', function () {
    expect(LaravelVersionResolver::normalizeVersion('11.x'))->toBe('11.0.0');

    expect(LaravelVersionResolver::normalizeVersion('13.x'))->toBe('13.0.0');
});

it('returns the current major laravel branch', function () {
    $resolver = new LaravelVersionResolver;

    $expectedBranch = explode('.', app()->version())[0].'.x';

    expect($resolver->currentBranch())->toBe($expectedBranch);
});

it('uses the github default branch', function () {
    config(['x-ray.cache.enabled' => false]);

    Http::fake([
        'api.github.com/repos/laravel/laravel' => Http::response([
            'default_branch' => '13.x',
        ]),
    ]);

    $resolver = new LaravelVersionResolver;

    expect($resolver->targetBranch())->toBe('13.x');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.github.com/repos/laravel/laravel';
    });
});
