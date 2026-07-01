<?php

namespace Mmstewart\LaravelXRay\Commands;

use Illuminate\Console\Command;
use Mmstewart\LaravelXRay\SkeletonDifference;
use Mmstewart\LaravelXRay\Categorizer;

class LaravelXRayCommand extends Command
{
    public $signature = 'laravel-x-ray {from=10.x} {to=11.x}';

    public $description = 'Scan your Laravel app for upgrade compatibility';

    public function handle()
    {
        $from = $this->argument('from');
        $to = $this->argument('to');

        if (!$this->isValidUpgrade($from, $to)) {
            return self::FAILURE;
        }

        $this->info("Running X-Ray scan: Laravel {$from} → {$to}");

        $files = (new SkeletonDifference(config('x-ray.github_token')))->fetch($from, $to);

        $categorized = (new Categorizer())->categorize($files);

        $this->line('Config changes:');
        foreach ($categorized['config'] as $file) {
            $this->line("  - {$file['filename']}");
        }

        $this->line('Composer changes:');
        foreach ($categorized['composer'] as $file) {
            $this->line("  - {$file['filename']}");
        }

        $this->line('Environment changes:');
        foreach ($categorized['env'] as $file) {
            $this->line("  - {$file['filename']}");
        }

        $this->line('Bootstrap changes:');
        foreach ($categorized['bootstrap'] as $file) {
            $this->line("  - {$file['filename']}");
        }

        // $results = array_merge(
        //     (new EnvAnalyzer($categorized['env']))->analyze(),
        //     (new ComposerAnalyzer($categorized['composer'], $to))->analyze(),
        //     (new ConfigAnalyzer($categorized['config']))->analyze(),
        //     (new BootstrapAnalyzer($categorized['bootstrap']))->analyze(),
        // );

        // (new XRayReport($this))->display($results);

        // return self::SUCCESS;


        // $diff = (new LaravelXRayComparisonUpgradeDifference(
        //     config('x-ray.github_token')
        // ))->fetch($from, $to);

        // foreach ($diff as $file) {
        //     $this->line($file['filename']);
        // }

        $this->info('Done!');

        return self::SUCCESS;
    }

    private function isValidUpgrade(string $from, string $to)
    {
        $validVersions = config('x-ray.valid_laravel_versions');

        if (!in_array($from, $validVersions)) {
            $this->error("Invalid version: {$from}. Valid versions are: " . implode(', ', $validVersions));

            return false;
        }

        if (!in_array($to, $validVersions)) {
            $this->error("Invalid version: {$to}. Valid versions are: " . implode(', ', $validVersions));

            return false;
        }

        if ($from >= $to) {
            $this->error("You must upgrade to a higher version. {$from} is not lower than {$to}");

            return false;
        }

        return true;
    }
}
