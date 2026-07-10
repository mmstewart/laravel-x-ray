<?php

namespace Mmstewart\LaravelXRay\Reports;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class LaravelXRayReport
{
    public function __construct(
        private Command $command,
    ) {}

    public function display(array $results): void
    {
        $severityLevels = ['info' => 0, 'warning' => 1, 'error' => 2];
        $minimum = config('x-ray.minimum_severity', 'info');

        $results = collect($results)
            ->filter(fn ($issue) => isset($severityLevels[$issue['severity']])
                && $severityLevels[$issue['severity']] >= $severityLevels[$minimum]
            )
            ->toArray();

        $errors = collect($results)->where('severity', 'error');
        $warnings = collect($results)->where('severity', 'warning');
        $info = collect($results)->where('severity', 'info');

        $this->command->line('');
        $this->command->line(str_repeat('─', 50));
        $this->command->line('Laravel X-Ray Upgrade Report');
        $this->command->line(str_repeat('─', 50));
        $this->command->line('');

        $this->renderSection('ERRORS', '❌', $errors);
        $this->renderSection('WARNINGS', '⚠️ ', $warnings);
        $this->renderSection('INFO', 'ℹ️', $info);

        if (collect($results)->isEmpty()) {
            $this->command->info('✅ No issues found — you are ready to upgrade!');
            $this->command->line('');
        }

        $this->command->line(str_repeat('─', 50));
        $this->command->line("{$errors->count()} errors, {$warnings->count()} warnings, {$info->count()} info");
        $this->command->line('');
    }

    private function renderSection(string $title, string $icon, Collection $issues): void
    {
        if ($issues->isEmpty()) {
            return;
        }

        $this->command->line("{$icon} {$title} ({$issues->count()})");

        $issues->each(
            fn ($issue) => $this->command->line("  • {$issue['message']}")
        );

        $this->command->line('');
    }
}
