<?php

namespace Mmstewart\LaravelXRay\Analyzers;

class EnvAnalyzer
{
    public function __construct(
        private array $skeletonFiles
    ) {}

    public function analyze(): array
    {
        $patch = $this->getEnvPatch();

        if (! $patch) {
            return [];
        }

        $missingKeys = array_diff(
            $this->parseAddedKeys($patch),
            $this->getUserEnvKeys()
        );

        return collect($missingKeys)
            ->map(fn ($key) => [
                'type' => 'env',
                'severity' => 'warning',
                'message' => "Missing env key: {$key}",
                'key' => $key,
            ])
            ->values()
            ->toArray();
    }

    private function getEnvPatch(): ?string
    {
        return collect($this->skeletonFiles)
            ->firstWhere('filename', '.env.example')['patch'] ?? null;
    }

    // Pull keys from lines added in the patch (lines starting with +)
    private function parseAddedKeys(string $patch): array
    {
        $keys = [];

        foreach (explode("\n", $patch) as $line) {
            if (! str_starts_with($line, '+') || str_starts_with($line, '+++')) {
                continue;
            }

            $line = trim(ltrim($line, '+'));

            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            $keys[] = trim(strtok($line, '='));
        }

        return array_unique($keys);
    }

    // Read the user's actual .env.example and extract keys
    private function getUserEnvKeys(): array
    {
        $path = base_path('.env.example');

        if (! file_exists($path)) {
            return [];
        }

        $keys = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);

            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            $keys[] = trim(strtok($line, '='));
        }

        return array_unique($keys);
    }
}
