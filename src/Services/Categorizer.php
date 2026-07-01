<?php

namespace Mmstewart\LaravelXRay;

class Categorizer
{
    public function categorize(array $files)
    {
        $result = [
            'config' => [],
            'bootstrap' => [],
            'composer' => [],
            'env' => [],
            'other' => [],
        ];

        foreach ($files as $file) {
            $bucket = $this->detectBucket($file['filename']);

            $result[$bucket][] = $file;
        }

        return $result;
    }

    private function detectBucket(string $filename)
    {
        return match (true) {
            str_starts_with($filename, 'config/') => 'config',
            $filename === 'bootstrap/app.php' => 'bootstrap',
            $filename === 'composer.json' => 'composer',
            $filename === '.env.example' => 'env',
            default => 'other',
        };
    }
}
