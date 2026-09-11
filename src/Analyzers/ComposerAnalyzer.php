<?php

namespace Mmstewart\LaravelXRay\Analyzers;

use Composer\Semver\Semver;
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

    private function getInstalledPackageVersion(string $package): ?string
    {
        $lockPath = base_path('composer.lock');

        if (! file_exists($lockPath)) {
            return null;
        }

        $lock = json_decode(file_get_contents($lockPath), true) ?? [];

        foreach ([
            ...($lock['packages'] ?? []),
            ...($lock['packages-dev'] ?? []),
        ] as $installed) {
            if (($installed['name'] ?? null) === $package) {
                return ltrim($installed['version'], 'v');
            }
        }

        return null;
    }

    private function buildCompatibilityIssue(string $package, string $installedConstraint): ?array
    {
        $skeletonComposer = $this->getSkeletonComposer();

        // Laravel skeleton dependency
        if (isset($skeletonComposer['require'][$package])) {
            $recommended = $skeletonComposer['require'][$package];

            if ($installedConstraint === $recommended) {
                return null;
            }

            return [
                'type' => 'composer',
                'severity' => 'warning',
                'message' => "{$package} ({$installedConstraint}) requires adjustment for Laravel {$this->targetVersion} compatibility. Recommended constraint: {$recommended}.",
                'key' => $package,
            ];
        }

        // Third-party dependency
        $recommended = $this->findCompatibleVersion($package, $this->targetVersion);

        if ($recommended === null) {
            return [
                'type' => 'composer',
                'severity' => 'info',
                'message' => "{$package} could not be checked for Laravel {$this->targetVersion} compatibility.",
                'key' => $package,
            ];
        }

        if ($this->satisfiesConstraint($recommended, $installedConstraint)) {
            return null;
        }

        return [
            'type' => 'composer',
            'severity' => 'warning',
            'message' => "{$package} ({$installedConstraint}) requires adjustment for Laravel {$this->targetVersion} compatibility. Recommended version: {$this->recommendedConstraint($recommended)}.",
            'key' => $package,
        ];
    }

    private function recommendedConstraint(string $version): string
    {
        return '^'.ltrim($version, 'v');
    }

    private function checkDevDependencyVersions(): array
    {
        $skeletonDevDeps = $this->getSkeletonComposer()['require-dev'] ?? [];

        $userDevDeps = $this->getUserDevPackages();

        return collect($skeletonDevDeps)
            ->filter(fn ($constraint, $package) => isset($userDevDeps[$package]))
            ->filter(fn ($constraint, $package) => $userDevDeps[$package] !== $constraint
            )
            ->map(fn ($constraint, $package) => [
                'type' => 'composer',
                'severity' => 'warning',
                'message' => "{$package} ({$userDevDeps[$package]}) requires adjustment for Laravel {$this->targetVersion} compatibility. Recommended constraint: {$constraint}.",
                'key' => $package,
            ])
            ->values()
            ->toArray();
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

        foreach ($versions as $version => $details) {
            if (str_contains($version, '-dev')) {
                continue;
            }

            $requires = $details['require'] ?? [];

            $laravelConstraint = $this->getLaravelConstraint($requires);

            if (! $laravelConstraint) {
                continue;
            }

            if ($this->satisfiesConstraint(
                LaravelVersionResolver::normalizeVersion($targetLaravel),
                $laravelConstraint
            )) {
                return $version;
            }
        }

        return null;
    }

    private function getLaravelConstraint(array $requires): ?string
    {
        foreach ($requires as $package => $constraint) {
            if (
                $package === 'laravel/framework'
                || str_starts_with($package, 'illuminate/')
            ) {
                return $constraint;
            }
        }

        return null;
    }
}
