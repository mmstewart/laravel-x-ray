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
    public $signature = 'laravel-x-ray {from?} {to?} {--format=table} {--fail-on= : Minimum severity that causes a non-zero exit code (error, warning, info)}';

    public $description = 'Scan your Laravel app for upgrade compatibility.';

    public function handle(): int
    {
        $format = $this->option('format');

        $failOn = $this->option('fail-on');

        if (! in_array($format, ['table', 'json'], true)) {
            $this->error('Invalid format. Supported formats: table, json.');

            return self::FAILURE;
        }

        if ($failOn !== null && ! in_array($failOn, ['error', 'warning', 'info'], true)) {
            $this->error('Invalid failure threshold. Supported values: error, warning, info.');

            return self::FAILURE;
        }

        $versionResolver = new LaravelVersionResolver;

        $context = new LaravelContext(installedVersion: $versionResolver->version(), currentBranch: $versionResolver->currentBranch(), targetBranch: $versionResolver->targetBranch());

        [$from, $to] = $this->resolveBranches($context);

        if (! $this->isValidUpgrade($from, $to)) {
            return self::FAILURE;
        }

        if ($format === 'table') {
            $this->info("Laravel {$context->installedVersion}");
            $this->info("Comparing {$from} → {$to}");
        }

        if ($from === $to) {
            $this->warn('No newer Laravel skeleton branch is available.');

            return self::SUCCESS;
        }

        $skeletonFiles = $this->fetchSkeletonDiff($from, $to);

        $categorizedFiles = (new LaravelSkeletonCategorizer)->categorize($skeletonFiles);

        $analyzers = $this->buildAnalyzers($to, $categorizedFiles);

        $results = $this->runAnalyzers($analyzers);

        $this->renderReport($results, $format);

        if ($failOn === null) {
            return self::SUCCESS;
        }

        return $this->reportFailureThreshold($results, $failOn, $format);
    }

    /**
     * Report whether findings meet the configured failure threshold.
     *
     * @param array<int,mixed> $results Analyzer findings
     * @param string $failOn Minimum severity that triggers failure
     * @param string $format Report output format
     * @return int Command exit code
     */
    private function reportFailureThreshold(array $results, string $failOn, string $format): int
    {
        $failureCounts = $this->getFailureCounts($results, $failOn);

        $failed = array_sum($failureCounts) > 0;

        if ($format === 'json') {
            return $failed ? self::FAILURE : self::SUCCESS;
        }

        if (! $failed) {
            $this->info("SUCCESS: No findings meet the configured failure threshold (--fail-on={$failOn}).");

            return self::SUCCESS;
        }

        $counts = [];

        foreach ($failureCounts as $severity => $count) {
            if ($count > 0) {
                $label = match ($severity) {
                    'error' => $count === 1 ? '1 error' : "{$count} errors",
                    'warning' => $count === 1 ? '1 warning' : "{$count} warnings",
                    'info' => $count === 1 ? '1 info finding' : "{$count} info findings",
                };

                $counts[] = $label;
            }
        }

        $this->error('FAILURE: The report contains ' . implode(', ', $counts) . " at or above the configured failure threshold (--fail-on={$failOn}).");

        return self::FAILURE;
    }

    /**
     * Count findings that meet the configured failure threshold.
     *
     * Only findings that meet both the requested failure threshold and the
     * configured minimum severity are included in the counts.
     *
     * @param array<int,mixed> $results Analyzer findings
     * @param string $failOn Minimum severity that triggers failure
     * @return array<string,int> Counts grouped by severity
     */
    private function getFailureCounts(array $results, string $failOn): array
    {
        $severityLevels = [
            'info' => 0,
            'warning' => 1,
            'error' => 2,
        ];

        $minimum = config('x-ray.minimum_severity', 'info');

        $counts = [
            'error' => 0,
            'warning' => 0,
            'info' => 0,
        ];

        foreach ($results as $issue) {
            $severity = $issue['severity'] ?? null;

            if (! isset($severityLevels[$severity]) || $severityLevels[$severity] < $severityLevels[$failOn] || $severityLevels[$severity] < $severityLevels[$minimum]) {
                continue;
            }

            $counts[$severity]++;
        }

        return $counts;
    }

    /**
     * Determine whether the requested upgrade path is valid.
     *
     * This performs a basic major-version comparison to ensure the target
     * Laravel major version is greater than the source. It currently assumes
     * branch names start with the major version (e.g. "11.x", "12.x").
     *
     * @param string $from Source branch or version
     * @param string $to Target branch or version
     * @return bool True when upgrade is valid, false otherwise
     */
    private function isValidUpgrade(string $from, string $to): bool
    {
        if (! preg_match('/^\d+\.x$/i', $from)) {
            $this->error("Invalid Laravel version: {$from}");
            $this->line('Use a Laravel version in the format 11.x, 12.x, or 13.x.');

            return false;
        }

        if (! preg_match('/^\d+\.x$/i', $to)) {
            $this->error("Invalid Laravel version: {$to}");
            $this->line('Use a Laravel version in the format 11.x, 12.x, or 13.x.');

            return false;
        }

        if ((int) $to < (int) $from) {
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

        return [strtolower($from), strtolower($to)];
    }

    /**
     * Fetch skeleton diff between branches.
     *
     * @return array<int,array>
     */
    private function fetchSkeletonDiff(string $from, string $to): array
    {
        return (new LaravelSkeletonDifference)->fetch($from, $to);
    }

    /**
     * Build the analyzers registry for execution.
     *
     * @param array<string,mixed> $categorized
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
     * @param array<string,callable> $analyzers
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
     * @param array<int,mixed> $results Analyzer results to render
     * @param string $format Report format (table or json)
     */
    private function renderReport(array $results, string $format = 'table'): void
    {
        (new LaravelXRayReport($this))->display($results, $format);
    }
}
