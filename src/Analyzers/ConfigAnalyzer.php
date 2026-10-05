<?php

namespace Mmstewart\LaravelXRay\Analyzers;

class ConfigAnalyzer
{
    public function __construct(
        private array $skeletonFiles,
        private string $targetVersion
    ) {}

    public function analyze(): array
    {
        return collect($this->skeletonFiles)
            ->filter(fn ($file) => str_starts_with($file['filename'] ?? '', 'config/'))
            ->flatMap(fn ($file) => $this->analyzeFile($file))
            ->values()
            ->toArray();
    }

    private function analyzeFile(array $file): array
    {
        $filename = $file['filename'];
        $status = $file['status'] ?? null;
        $exists = file_exists(base_path($filename));

        if ($status === 'removed' && $exists) {
            return [
                [
                    'type' => 'config',
                    'severity' => 'info',
                    'message' => "Config file {$filename} was removed from the Laravel {$this->targetVersion} skeleton. Review whether it is still needed.",
                    'key' => $filename,
                ],
            ];
        }

        if ($status === 'added' && ! $exists) {
            return [
                [
                    'type' => 'config',
                    'severity' => 'info',
                    'message' => "Config file {$filename} was added to the Laravel {$this->targetVersion} skeleton. Review whether it is needed.",
                    'key' => $filename,
                ],
            ];
        }

        if ($status === 'modified' && $exists) {
            return $this->analyzeModifiedFile($file);
        }

        return [];
    }

    private function analyzeModifiedFile(array $file): array
    {
        return match ($file['filename']) {
            'config/services.php' => $this->analyzeServicesConfig($file),
            'config/logging.php' => $this->analyzeLoggingConfig($file),
            'config/session.php' => $this->analyzeSessionConfig($file),
            'config/cache.php' => $this->analyzeCacheConfig($file),
            default => [],
        };
    }

    private function analyzeServicesConfig(array $file): array
    {
        $patch = $file['patch'] ?? '';
        $results = [];

        if (
            str_contains($patch, 'POSTMARK_TOKEN')
            && str_contains($patch, 'POSTMARK_API_KEY')
        ) {
            $results[] = [
                'type' => 'config',
                'severity' => 'warning',
                'message' => "Laravel {$this->targetVersion} changed the Postmark environment variable from POSTMARK_TOKEN to POSTMARK_API_KEY. Update your .env configuration if you use Postmark.",
                'key' => $file['filename'],
            ];
        }

        if (
            str_contains($patch, 'RESEND_KEY')
            && str_contains($patch, 'RESEND_API_KEY')
        ) {
            $results[] = [
                'type' => 'config',
                'severity' => 'warning',
                'message' => "Laravel {$this->targetVersion} changed the Resend environment variable from RESEND_KEY to RESEND_API_KEY. Update your .env configuration if you use Resend.",
                'key' => $file['filename'],
            ];
        }

        return $results;
    }

    private function analyzeLoggingConfig(array $file): array
    {
        $patch = $file['patch'] ?? '';

        if (
            str_contains($patch, "'days' => env('LOG_DAILY_DAYS'")
            && str_contains($patch, "'max_files' => env('LOG_DAILY_DAYS'")
        ) {
            return [[
                'type' => 'config',
                'severity' => 'warning',
                'message' => "Laravel {$this->targetVersion} changed the daily logging configuration from days to max_files. Review your log retention configuration.",
                'key' => $file['filename'],
            ]];
        }

        return [];
    }

    private function analyzeSessionConfig(array $file): array
    {
        $patch = $file['patch'] ?? '';

        if (str_contains($patch, "'serialization' => 'json'")) {
            return [[
                'type' => 'config',
                'severity' => 'info',
                'message' => "Laravel {$this->targetVersion} introduced a session serialization setting. Review your session configuration if your application stores PHP objects in sessions.",
                'key' => $file['filename'],
            ]];
        }

        return [];
    }

    private function analyzeCacheConfig(array $file): array
    {
        $patch = $file['patch'] ?? '';

        if (str_contains($patch, "'serializable_classes' => false")) {
            return [[
                'type' => 'config',
                'severity' => 'info',
                'message' => "Laravel {$this->targetVersion} introduced a cache serializable_classes setting. Review your cache configuration if your application stores serialized PHP objects in the cache.",
                'key' => $file['filename'],
            ]];
        }

        return [];
    }
}