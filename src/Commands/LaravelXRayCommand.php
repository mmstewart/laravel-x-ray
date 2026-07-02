<?php

namespace Mmstewart\LaravelXRay\Commands;

use Illuminate\Console\Command;
use Mmstewart\LaravelXRay\Analyzers\ComposerAnalyzer;
use Mmstewart\LaravelXRay\Analyzers\EnvAnalyzer;
use Mmstewart\LaravelXRay\Services\Categorizer;
use Mmstewart\LaravelXRay\Services\LaravelVersionDifference;

class LaravelXRayCommand extends Command
{
    public $signature = 'laravel-x-ray {from=10.x} {to=11.x}';

    public $description = 'Scan your Laravel app for upgrade compatibility';

    public function handle()
    {
        $from = $this->argument('from');
        $to = $this->argument('to');

        if (! $this->isValidUpgrade($from, $to)) {
            return self::FAILURE;
        }

        $this->info("Running X-Ray scan: Laravel {$from} → {$to}");

        $files = (new LaravelVersionDifference)->fetch($from, $to);

        // foreach ($files as $file) {
        //     $this->line($file['filename']);
        // }

        $categorized = (new Categorizer)->categorize($files);

        // foreach ($categorized as $bucket => $files) {
        //     $this->line("=== {$bucket} ===");

        //     foreach ($files as $file) {
        //         $this->line($file['filename']);
        //     }
        // }

        $analyzers = [
            'env' => fn() => (new EnvAnalyzer($categorized['env']))->analyze(),
            'composer' => fn() => (new ComposerAnalyzer($categorized['composer'], $to))->analyze(),
            // 'config' => fn() => (new ConfigAnalyzer($categorized['config']))->analyze(),
            // 'bootstrap' => fn() => (new BootstrapAnalyzer($categorized['bootstrap']))->analyze(),
        ];

        $results = collect($analyzers)
            ->filter(fn($analyzer, $key) => config("x-ray.analyzers.{$key}"))
            ->flatMap(fn($analyzer) => $analyzer())
            ->all();

        $this->line(print_r($results, true));

        // (new XRayReport($this))->display($results);

        return self::SUCCESS;
    }

    private function isValidUpgrade(string $from, string $to)
    {
        $validVersions = config('x-ray.valid_laravel_versions');

        if (! in_array($from, $validVersions)) {
            $this->error("Invalid version: {$from}. Valid versions are: ".implode(', ', $validVersions));

            return false;
        }

        if (! in_array($to, $validVersions)) {
            $this->error("Invalid version: {$to}. Valid versions are: ".implode(', ', $validVersions));

            return false;
        }

        if ($from >= $to) {
            $this->error("You must upgrade to a higher version. {$from} is not lower than {$to}");

            return false;
        }

        return true;
    }
}
