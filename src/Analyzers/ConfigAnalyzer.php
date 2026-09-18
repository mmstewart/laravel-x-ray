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
            return [
                [
                    'type' => 'config',
                    'severity' => 'info',
                    'message' => "Config file {$filename} was modified in the Laravel {$this->targetVersion} skeleton. Review your configuration for changes.",
                    'key' => $filename,
                ],
            ];
        }

        return [];
    }
}
