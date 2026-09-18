<?php

namespace Mmstewart\LaravelXRay\Analyzers;

class BootstrapAnalyzer
{
    public function __construct(
        private array $skeletonFiles
    ) {}

    private array $criticalMethods = [
        'withRouting',
        'withMiddleware',
        'withExceptions',
    ];

    public function analyze(): array
    {
        if (empty($this->skeletonFiles)) {
            return [];
        }

        $userBootstrap = $this->getUserBootstrap();

        if ($userBootstrap === null) {
            return [];
        }

        if ($this->isLegacyBootstrap($userBootstrap)) {
            return [
                [
                    'type' => 'bootstrap',
                    'severity' => 'error',
                    'message' => 'bootstrap/app.php is using the legacy Laravel bootstrap pattern. It needs to be migrated to Application::configure().',
                    'key' => 'bootstrap/app.php',
                ],
            ];
        }

        $patch = $this->skeletonFiles[0]['patch'] ?? '';

        return collect($this->parseAddedMethods($patch))
            ->reject(fn ($method) => str_contains($userBootstrap, "->{$method}("))
            ->map(fn ($method) => [
                'type' => 'bootstrap',
                'severity' => 'warning',
                'message' => $this->getMessage($method),
                'key' => $method,
            ])
            ->values()
            ->toArray();
    }

    private function parseAddedMethods(string $patch): array
    {
        preg_match_all('/^\+.*->([A-Za-z_][A-Za-z0-9_]*)\s*\(/m', $patch, $matches);

        return array_values(array_unique(
            array_intersect($matches[1], $this->criticalMethods)
        ));
    }

    private function getMessage(string $method): string
    {
        return "bootstrap/app.php is missing ->{$method}() configuration.";
    }

    private function getUserBootstrap(): ?string
    {
        $path = base_path('bootstrap/app.php');

        if (! file_exists($path)) {
            return null;
        }

        return file_get_contents($path);
    }

    private function isLegacyBootstrap(string $content): bool
    {
        return str_contains($content, 'new Illuminate\Foundation\Application(') && ! str_contains($content, 'Application::configure(');
    }
}
