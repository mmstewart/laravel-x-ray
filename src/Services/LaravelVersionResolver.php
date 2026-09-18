<?php

namespace Mmstewart\LaravelXRay\Services;

use Illuminate\Support\Facades\Cache;

class LaravelVersionResolver
{
    public function version(): string
    {
        return app()->version();
    }

    public static function normalizeVersion(string $version): string
    {
        return str_replace('.x', '.0.0', $version);
    }

    public function currentBranch(): string
    {
        return explode('.', $this->version())[0].'.x';
    }

    public function targetBranch(): string
    {
        if (! config('x-ray.cache.enabled')) {
            return $this->fetchTargetBranch();
        }

        return Cache::remember(
            'xray:default_branch',
            now()->addMinutes(config('x-ray.cache.default_branch')),
            fn () => $this->fetchTargetBranch()
        );
    }

    private function fetchTargetBranch(): string
    {
        return app(GithubClient::class)->repository('laravel/laravel')->json('default_branch', 'main');
    }
}
