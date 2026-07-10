<?php

namespace Mmstewart\LaravelXRay\Services;

use Illuminate\Support\Facades\Cache;

class LaravelVersionResolver
{
    public function version(): string
    {
        return app()->version();
    }

    public function currentBranch(): string
    {
        return explode('.', $this->version())[0] . '.x';
    }

    public function targetBranch(): string
    {
        return Cache::remember(
            'xray:default_branch',
            now()->addMinutes(config('x-ray.cache.default_branch')),
            fn () => app(GithubClient::class)
                ->repository('laravel/laravel')
                ->json('default_branch')
        );
    }

    public static function normalizeVersion(string $version): string
    {
        return str_replace('.x', '.0.0', $version);
    }
}