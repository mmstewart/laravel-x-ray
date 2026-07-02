<?php

namespace Mmstewart\LaravelXRay\Analyzers;

use Composer\Semver\Semver;
use Illuminate\Support\Facades\Http;

class ComposerAnalyzer
{
    public function __construct(
        private array $skeletonFiles,
        private string $targetVersion
    ) {}

    public function analyze()
    {
        return collect([
            $this->checkPhpVersion(),
            $this->checkPackageCompatibility(),
            $this->checkDevDependencyVersions(),
        ])->flatten(1)->toArray();
    }

    // PHP version check — if the skeleton requires a higher PHP version than the user has, that's an issue
    private function checkPhpVersion()
    {
        $requiredPhp = $this->parseRequiredPhpFromPatch();

        if (! $requiredPhp || $this->satisfiesConstraint(PHP_VERSION, $requiredPhp)) {
            return [];
        }

        return [
            [
                'type' => 'composer',
                'severity' => 'error',
                'message' => "PHP {$requiredPhp} required but you are running ".PHP_VERSION,
                'key' => 'php',
            ],
        ];
    }

    private function parseRequiredPhpFromPatch()
    {
        $patch = $this->skeletonFiles[0]['patch'] ?? '';

        foreach (explode("\n", $patch) as $line) {
            // Look for added lines that mention php version requirement
            if (str_starts_with($line, '+') && ! str_starts_with($line, '+++')) {
                if (str_contains($line, '"php"')) {
                    // Extract version constraint e.g. "^8.2"
                    preg_match('/"php":\s*"([^"]+)"/', $line, $matches);

                    return $matches[1] ?? null;
                }
            }
        }

        return null;
    }

    // Simple constraint check — handles ^ and >= for now
    private function satisfiesConstraint(string $actual, string $required)
    {
        try {
            return Semver::satisfies($actual, $required);
        } catch (\Exception $e) {
            return false;
        }
    }

    // Check each package in the user's composer.json to see if it has a version that supports the target Laravel version
    private function checkPackageCompatibility()
    {
        $skip = ['php', 'laravel/framework', 'mmstewart/laravel-x-ray', 'composer/semver'];

        return collect($this->getUserPackages())
            ->reject(fn ($version, $package) => in_array($package, $skip))
            ->map(fn ($version, $package) => $this->buildCompatibilityIssue($package))
            ->filter()
            ->values()
            ->toArray();
    }

    private function buildCompatibilityIssue(string $package)
    {
        $compatible = $this->isPackageCompatible($package, $this->targetVersion);

        return match ($compatible) {
            false => [
                'type' => 'composer',
                'severity' => 'error',
                'message' => "{$package} has no version that supports Laravel {$this->targetVersion}",
                'key' => $package,
            ],
            null => [
                'type' => 'composer',
                'severity' => 'info',
                'message' => "{$package} could not be checked on Packagist",
                'key' => $package,
            ],
            default => null,
        };
    }

    private function checkDevDependencyVersions()
    {
        $skeletonDevDeps = $this->getSkeletonComposer()['require-dev'] ?? [];

        $userDevDeps = $this->getUserDevPackages();

        return collect($skeletonDevDeps)
            ->filter(fn ($skeletonVersion, $package) => isset($userDevDeps[$package]))
            ->reject(fn ($skeletonVersion, $package) => $userDevDeps[$package] === $skeletonVersion)
            ->map(fn ($skeletonVersion, $package) => [
                'type' => 'composer',
                'severity' => 'warning',
                'message' => "{$package} version mismatch — you have {$userDevDeps[$package]}, Laravel {$this->targetVersion} recommends {$skeletonVersion}",
                'key' => $package,
            ])->values()->toArray();
    }

    private function getUserDevPackages()
    {
        $path = base_path('composer.json');

        if (! file_exists($path)) {
            return [];
        }

        $composer = json_decode(file_get_contents($path), true);

        return $composer['require-dev'] ?? [];
    }

    private function getSkeletonComposer()
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('x-ray.github_token'),
            'Accept' => 'application/vnd.github+json',
        ])->get("https://api.github.com/repos/laravel/laravel/contents/composer.json?ref={$this->targetVersion}");

        $content = base64_decode($response->json('content'));

        return json_decode($content, true);
    }

    // Only check production dependencies, not dev
    private function getUserPackages()
    {
        $path = base_path('composer.json');

        if (! file_exists($path)) {
            return [];
        }

        $composer = json_decode(file_get_contents($path), true);

        return $composer['require'] ?? [];
    }

    // Returns true = compatible, false = incompatible, null = unknown
    private function isPackageCompatible(string $package, string $targetLaravel)
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->get("https://packagist.org/packages/{$package}.json");

        if (! $response->successful()) {
            return null;
        }

        foreach ($response->json('package.versions', []) as $version) {
            $laravelConstraint = $version['require']['laravel/framework'] ?? $version['require']['illuminate/support'] ?? null;

            if ($laravelConstraint && $this->satisfiesConstraint($this->normalizeVersion($targetLaravel), $laravelConstraint)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeVersion(string $version)
    {
        // Convert '12.x' to '12.0.0'
        return str_replace('.x', '.0.0', $version);
    }
}
