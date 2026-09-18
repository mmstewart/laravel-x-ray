<?php

namespace Mmstewart\LaravelXRay\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class GithubClient
{
    public function compare(string $repository, string $from, string $to): Response
    {
        return Http::withHeaders($this->headers())->get("https://api.github.com/repos/{$repository}/compare/{$from}...{$to}");
    }

    public function repository(string $repository): Response
    {
        return Http::withHeaders($this->headers())->get("https://api.github.com/repos/{$repository}");
    }

    private function headers(): array
    {
        $headers = [
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
        ];

        if ($token = config('x-ray.github_token')) {
            $headers['Authorization'] = 'Bearer '.$token;
        }

        return $headers;
    }
}
