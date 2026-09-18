<?php

namespace Mmstewart\LaravelXRay\Commands;

use Illuminate\Console\Command;
use Mmstewart\LaravelXRay\Analyzers\BootstrapAnalyzer;
use Mmstewart\LaravelXRay\Analyzers\ComposerAnalyzer;
use Mmstewart\LaravelXRay\Analyzers\ConfigAnalyzer;
use Mmstewart\LaravelXRay\Analyzers\EnvAnalyzer;
use Mmstewart\LaravelXRay\Reports\LaravelXRayReport;
use Mmstewart\LaravelXRay\Services\LaravelContext;
use Mmstewart\LaravelXRay\Services\LaravelSkeletonCategorizer;
use Mmstewart\LaravelXRay\Services\LaravelSkeletonDifference;
use Mmstewart\LaravelXRay\Services\LaravelVersionResolver;

class LaravelXRayCommand extends Command
{
    public $signature = 'laravel-x-ray {from?} {to?}';

    public $description = 'Scan your Laravel app for upgrade compatibility.';

    public function handle(): int
    {
        $versionResolver = new LaravelVersionResolver;

        $context = new LaravelContext(
            installedVersion: $versionResolver->version(),
            currentBranch: $versionResolver->currentBranch(),
            targetBranch: $versionResolver->targetBranch(),
        );

        [$from, $to] = $this->resolveBranches($context);

        if (! $this->isValidUpgrade($from, $to)) {
            return self::FAILURE;
        }

        $this->info("Laravel {$context->installedVersion}");
        $this->info("Comparing {$from} → {$to}");

        if ($from === $to) {
            $this->warn('No newer Laravel skeleton branch is available.');

            return self::SUCCESS;
        }

        $skeletonFiles = $this->fetchSkeletonDiff($from, $to);

        $categorizedFiles = (new LaravelSkeletonCategorizer)->categorize($skeletonFiles);

        $analyzers = $this->buildAnalyzers($to, $categorizedFiles);

        $results = $this->runAnalyzers($analyzers);

        $this->renderReport($results);

        return self::SUCCESS;
    }

    /**
     * Determine whether the requested upgrade path is valid.
     *
     * This performs a basic major-version comparison to ensure the target
     * Laravel major version is greater than the source. It currently assumes
     * branch names start with the major version (e.g. "11.x", "12.x").
     *
     * @param  string  $from  Source branch or version
     * @param  string  $to  Target branch or version
     * @return bool True when upgrade is valid, false otherwise
     */
    private function isValidUpgrade(string $from, string $to): bool
    {
        $fromVersion = LaravelVersionResolver::normalizeVersion($from);

        $toVersion = LaravelVersionResolver::normalizeVersion($to);

        if (version_compare($fromVersion, $toVersion, '>')) {
            $this->error("Invalid upgrade path: {$from} → {$to}");

            return false;
        }

        return true;
    }

    /**
     * Resolve the source and target branches from context and arguments.
     *
     * @return array{0:string,1:string}
     */
    private function resolveBranches(LaravelContext $context): array
    {
        $from = $this->argument('from') ?? $context->currentBranch;

        $to = $this->argument('to') ?? $context->targetBranch;

        return [$from, $to];
    }

    /**
     * Fetch skeleton diff between branches.
     *
     * @return array<int, array>
     */
    private function fetchSkeletonDiff(string $from, string $to): array
    {
        return (new LaravelSkeletonDifference)->fetch($from, $to);
    }

    /**
     * Build the analyzers registry for execution.
     *
     * @param  array<string,mixed>  $categorized
     * @return array<string,callable>
     */
    private function buildAnalyzers(string $to, array $categorized): array
    {
        return [
            'env' => fn () => (new EnvAnalyzer($categorized['env']))->analyze(),
            'composer' => fn () => (new ComposerAnalyzer($categorized['composer'], $to))->analyze(),
            'config' => fn () => (new ConfigAnalyzer($categorized['config'], $to))->analyze(),
            'bootstrap' => fn () => (new BootstrapAnalyzer($categorized['bootstrap']))->analyze(),
        ];
    }

    /**
     * Run the configured analyzers honoring config toggles.
     *
     * @param  array<string,callable>  $analyzers
     * @return array<int,mixed>
     */
    private function runAnalyzers(array $analyzers): array
    {
        return collect($analyzers)
            ->filter(fn ($analyzer, $key) => config("x-ray.analyzers.{$key}"))
            ->flatMap(fn ($analyzer) => $analyzer())
            ->all();
    }

    /**
     * Render the analysis results to the console (or other report targets).
     *
     * The `LaravelXRayReport` is responsible for formatting and output; this
     * method delegates to it so the command remains focused on orchestration.
     *
     * @param  array<int,mixed>  $results  Analyzer results to render
     */
    private function renderReport(array $results): void
    {
        (new LaravelXRayReport($this))->display($results);
    }
}
