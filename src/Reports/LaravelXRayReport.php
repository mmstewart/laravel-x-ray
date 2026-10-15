<?php

namespace Mmstewart\LaravelXRay\Reports;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class LaravelXRayReport
{
    public function __construct(
        private Command $command,
    ) {}

    public function display(array $results, string $format = 'table'): void
    {
        $severityLevels = [
            'info' => 0,
            'warning' => 1,
            'error' => 2,
        ];

        $minimum = config('x-ray.minimum_severity', 'info');

        $results = collect($results)
            ->filter(fn ($issue) => isset($severityLevels[$issue['severity']]) && $severityLevels[$issue['severity']] >= $severityLevels[$minimum])
            ->values();

        $errors = $results->where('severity', 'error');
        $warnings = $results->where('severity', 'warning');
        $info = $results->where('severity', 'info');

        if ($format === 'json') {
            $this->command->line(json_encode([
                'summary' => [
                    'errors' => $errors->count(),
                    'warnings' => $warnings->count(),
                    'info' => $info->count(),
                ],
                'findings' => $results->values()->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return;
        }

        $this->command->line('');
        $this->command->line(str_repeat('─', 50));
        $this->command->line('Laravel X-Ray Upgrade Report');
        $this->command->line(str_repeat('─', 50));
        $this->command->line('');

        $this->renderSection('❌ REQUIRED ACTION', $errors);
        $this->renderSection('⚠️  CHANGES TO REVIEW', $warnings);
        $this->renderSection('ℹ️  OPTIONAL REVIEW', $info);

        match (true) {
            $errors->isNotEmpty() => $this->command->error("❌ {$errors->count()} errors require attention before upgrading."),
            $warnings->isNotEmpty() => $this->command->warn('⚠️  Review the warnings above before upgrading.'),
            default => $this->command->info('✅ No issues found — you are ready to upgrade!'),
        };

        $this->command->line('');

        $this->command->line(str_repeat('─', 50));
        $this->command->line("{$errors->count()} errors, {$warnings->count()} warnings, {$info->count()} info");
        $this->command->line('');
    }

    private function renderSection(string $title, Collection $issues): void
    {
        if ($issues->isEmpty()) {
            return;
        }

        $this->command->line($title);

        foreach ($issues->groupBy('type') as $type => $group) {
            $this->command->line('');
            $this->command->line('  '.$this->typeLabel($type));

            foreach ($group as $issue) {
                $this->command->line("    • {$issue['message']}");
            }
        }

        $this->command->line('');
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'composer' => 'DEPENDENCY CHANGES',
            'config' => 'CONFIGURATION CHANGES',
            'env' => 'ENVIRONMENT CHANGES',
            'bootstrap' => 'BOOTSTRAP CHANGES',
            default => 'OTHER CHANGES',
        };
    }
}
