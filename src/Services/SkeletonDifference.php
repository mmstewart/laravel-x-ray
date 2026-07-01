<?php

namespace Mmstewart\LaravelXRay;

use Illuminate\Support\Facades\Http;

class SkeletonDifference
{
    public function __construct(
        private string $token
    ) {}

    public function fetch(string $from, string $to)
    {
        $url = "https://api.github.com/repos/laravel/laravel/compare/{$from}...{$to}";

        // For future reference, see: https://docs.github.com/en/rest/about-the-rest-api/api-versions?apiVersion=2026-03-10#supported-api-versions

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Accept'        => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ])->get($url);

        return $response->json('files', []);
    }
}