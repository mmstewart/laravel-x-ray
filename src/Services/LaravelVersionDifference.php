<?php

namespace Mmstewart\LaravelXRay\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class LaravelVersionDifference
{
    public function fetch(string $from, string $to)
    {
        if (! config('x-ray.cache.enabled')) {
            return $this->fetchFromGithub($from, $to);
        }

        return Cache::remember(
            "laravel-xray:skeleton:{$from}:{$to}",
            config('x-ray.cache.duration'),
            fn () => $this->fetchFromGithub($from, $to)
        );
    }

    private function fetchFromGithub(string $from, string $to)
    {
        $url = "https://api.github.com/repos/laravel/laravel/compare/{$from}...{$to}";

        // For future reference, see: https://docs.github.com/en/rest/about-the-rest-api/api-versions?apiVersion=2026-03-10#supported-api-versions

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('x-ray.github_token'),
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ])->get($url);

        return $response->json('files', []);
    }
}
