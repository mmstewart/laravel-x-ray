<?php

namespace Mmstewart\LaravelXRay\Analyzers;

class EnvAnalyzer
{
    public function __construct(
        private array $skeletonFiles
    ) {}

    public function analyze()
    {
        if (empty($this->skeletonFiles)) {
            return [];
        }

        $patch = $this->skeletonFiles[0]['patch'] ?? '';

        $addedKeys = $this->parseAddedKeys($patch);

        $userKeys = $this->getUserEnvKeys();

        $missingKeys = array_diff($addedKeys, $userKeys);

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

    // Pull keys from lines added in the patch (lines starting with +)
    private function parseAddedKeys(string $patch)
    {
        $keys = [];

        foreach (explode("\n", $patch) as $line) {
            // Lines starting with + are additions, skip +++ header lines
            if (str_starts_with($line, '+') && ! str_starts_with($line, '+++')) {
                $line = ltrim($line, '+');
                $line = trim($line);

                // Skip empty lines and comments
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                // Extract the key from KEY=VALUE
                $key = explode('=', $line)[0];

                $keys[] = trim($key);
            }
        }

        return $keys;
    }

    // Read the user's actual .env.example and extract keys
    private function getUserEnvKeys()
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

            $keys[] = explode('=', $line)[0];
        }

        return $keys;
    }
}
