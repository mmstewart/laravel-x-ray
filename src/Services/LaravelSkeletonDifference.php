<?php

namespace Mmstewart\LaravelXRay\Services;

use Illuminate\Support\Facades\Cache;

class LaravelSkeletonDifference
{
    public function fetch(string $from, string $to): array
    {
        if (! config('x-ray.cache.enabled')) {
            return $this->fetchCompare($from, $to);
        }

        return Cache::remember(
            "xray:skeleton:{$from}:{$to}",
            now()->addMinutes(config('x-ray.cache.skeleton')),
            fn () => $this->fetchCompare($from, $to)
        );
    }

    private function fetchCompare(string $from, string $to): array
    {
        return app(GithubClient::class)
            ->compare(
                'laravel/laravel',
                $from,
                $to
            )
            ->json('files', []);
    }
}
