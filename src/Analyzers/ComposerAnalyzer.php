<?php

namespace Mmstewart\LaravelXRay\Analyzers;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mmstewart\LaravelXRay\Services\GithubClient;
use Mmstewart\LaravelXRay\Services\LaravelVersionResolver;

class ComposerAnalyzer
{
    public function __construct(
        private array $skeletonFiles,
        private string $targetVersion
    ) {}

    private ?array $composer = null;

    public function analyze(): array
    {
        return collect([
            $this->checkPhpVersion(),
            $this->checkPackageCompatibility(),
            $this->checkDevDependencyVersions(),
        ])->flatten(1)->toArray();
    }

    // PHP version check — if the skeleton requires a higher PHP version than the user has, that's an issue
    private function checkPhpVersion(): array
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

    private function parseRequiredPhpFromPatch(): ?string
    {
        $composer = collect($this->skeletonFiles)
            ->firstWhere('filename', 'composer.json');

        if (! $composer) {
            return null;
        }

        foreach (explode("\n", $composer['patch'] ?? '') as $line) {
            if (
                str_starts_with($line, '+')
                && ! str_starts_with($line, '+++')
                && preg_match('/"php":\s*"([^"]+)"/', $line, $matches)
            ) {
                return $matches[1];
            }
        }

        return null;
    }

    // Simple constraint check — handles ^ and >= for now
    private function satisfiesConstraint(string $actual, string $required): bool
    {
        try {
            return Semver::satisfies($actual, $required);
        } catch (\Throwable $e) {
            return false;
        }
    }

    // Check each package in the user's composer.json to see if it has a version that supports the target Laravel version
    private function checkPackageCompatibility(): array
    {
        $skip = ['php', 'laravel/framework', 'mmstewart/laravel-x-ray', 'composer/semver'];

        return collect($this->getUserPackages())
            ->reject(fn ($version, $package) => in_array($package, $skip))
            ->map(fn ($constraint, $package) => $this->buildCompatibilityIssue(
                $package,
                $constraint
            ))
            ->filter()
            ->values()
            ->toArray();
    }

    private function buildCompatibilityIssue(string $package, string $installedConstraint): ?array
    {
        $compatibleVersion = $this->findCompatibleVersion($package, $this->targetVersion);

        if ($compatibleVersion === null) {
            return [
                'type' => 'composer',
                'severity' => 'error',
                'message' => "{$package} has no version that supports Laravel {$this->targetVersion}",
                'key' => $package,
            ];
        }

        if (! Semver::satisfies($compatibleVersion, $installedConstraint)) {
            return [
                'type' => 'composer',
                'severity' => 'warning',
                'message' => "{$package} ({$installedConstraint}) should be upgraded to ^".explode('.', $compatibleVersion)[0].".{$compatibleVersion} before upgrading Laravel {$this->targetVersion}.",
                'key' => $package,
            ];
        }

        return null;
    }

    private function checkDevDependencyVersions(): array
    {
        $skeletonDevDeps = $this->getSkeletonComposer()['require-dev'] ?? [];

        $userDevDeps = $this->getUserDevPackages();

        return collect($skeletonDevDeps)
            ->filter(fn ($constraint, $package) => isset($userDevDeps[$package]))
            ->filter(fn ($constraint, $package) => ! $this->satisfiesDevDependency(
                $userDevDeps[$package],
                $constraint
            )
            )
            ->map(fn ($constraint, $package) => [
                'type' => 'composer',
                'severity' => 'warning',
                'message' => "{$package} ({$userDevDeps[$package]}) should be upgraded to {$constraint} before upgrading Laravel {$this->targetVersion}.",
                'key' => $package,
            ])
            ->values()
            ->toArray();
    }

    private function satisfiesDevDependency(string $installed, string $required): bool
    {
        try {
            $parser = new VersionParser;

            $installedConstraint = $parser->parseConstraints($installed);
            $requiredConstraint = $parser->parseConstraints($required);

            return $installedConstraint->matches($requiredConstraint);

        } catch (\Throwable) {
            return false;
        }
    }

    private function composer(): array
    {
        if ($this->composer !== null) {
            return $this->composer;
        }

        $path = base_path('composer.json');

        if (! file_exists($path)) {
            return $this->composer = [];
        }

        return $this->composer = json_decode(
            file_get_contents($path),
            true
        ) ?? [];
    }

    private function getUserDevPackages(): array
    {
        return $this->composer()['require-dev'] ?? [];
    }

    private function getSkeletonComposer(): array
    {
        if (! config('x-ray.cache.enabled')) {
            return $this->fetchSkeletonComposer();
        }

        return Cache::remember(
            "xray:skeleton:composer:{$this->targetVersion}",
            now()->addMinutes(config('x-ray.cache.skeleton')),
            fn () => $this->fetchSkeletonComposer()
        );
    }

    private function fetchSkeletonComposer(): array
    {
        $response = app(GithubClient::class)
            ->repository("laravel/laravel/contents/composer.json?ref={$this->targetVersion}");

        if (! $response->successful()) {
            return [];
        }

        $content = base64_decode($response->json('content', ''));

        return json_decode($content, true);
    }

    // Only check production dependencies, not dev
    private function getUserPackages(): array
    {
        return $this->composer()['require'] ?? [];
    }

    private function getPackagistVersions(string $package): ?array
    {
        if (! config('x-ray.cache.enabled')) {
            return $this->fetchPackagistVersions($package);
        }

        return Cache::remember(
            "xray:packagist:{$package}",
            now()->addMinutes(config('x-ray.cache.packagist')),
            fn () => $this->fetchPackagistVersions($package)
        );
    }

    private function fetchPackagistVersions(string $package): ?array
    {
        $response = Http::withHeaders([
            'Accept' => 'application/json',
        ])->get("https://packagist.org/packages/{$package}.json");

        if (! $response->successful()) {
            return null;
        }

        return $response->json('package.versions', []);
    }

    // Returns true = compatible, false = incompatible, null = unknown
    private function findCompatibleVersion(string $package, string $targetLaravel): ?string
    {
        $versions = $this->getPackagistVersions($package);

        if ($versions === null) {
            return null;
        }

        foreach ($versions as $version => $details) {
            $laravelConstraint = collect($details['require'] ?? [])
                ->filter(
                    fn ($constraint, $dependency) => $dependency === 'laravel/framework'
                        || str_starts_with($dependency, 'illuminate/')
                )
                ->first();

            if (
                $laravelConstraint &&
                $this->satisfiesConstraint(
                    LaravelVersionResolver::normalizeVersion($targetLaravel),
                    $laravelConstraint
                )
            ) {
                return $version;
            }
        }

        return null;
    }
}
