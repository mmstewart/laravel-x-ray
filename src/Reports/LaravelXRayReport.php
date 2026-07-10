<?php

namespace Mmstewart\LaravelXRay\Reports;

use Illuminate\Console\Command;

class LaravelXRayReport
{
    public function __construct(
        private Command $command
    ) {}

    public function display(array $results): void
    {
        $severityOrder = ['info' => 0, 'warning' => 1, 'error' => 2];
        $minimum = config('x-ray.minimum_severity', 'info');

        $results = collect($results)
            ->filter(fn($issue) => $severityOrder[$issue['severity']] >= $severityOrder[$minimum])
            ->toArray();

        $errors = collect($results)->where('severity', 'error');
        $warnings = collect($results)->where('severity', 'warning');
        $info = collect($results)->where('severity', 'info');

        $this->command->line('');
        $this->command->line(str_repeat('─', 50));
        $this->command->line('');

        if ($errors->isNotEmpty()) {
            $this->command->line("❌ ERRORS ({$errors->count()})");

            $errors->each(fn($issue) => $this->command->line("  • {$issue['message']}"));

            $this->command->line('');
        }

        if ($warnings->isNotEmpty()) {
            $this->command->line("⚠️  WARNINGS ({$warnings->count()})");

            $warnings->each(fn($issue) => $this->command->line("  • {$issue['message']}"));

            $this->command->line('');
        }

        if ($info->isNotEmpty()) {
            $this->command->line("ℹ️  INFO ({$info->count()})");

            $info->each(fn($issue) => $this->command->line("  • {$issue['message']}"));

            $this->command->line('');
        }

        if ($results === []) {
            $this->command->info('✅ No issues found — you are ready to upgrade!');
            $this->command->line('');
        }

        $this->command->line(str_repeat('─', 50));
        $this->command->line("{$errors->count()} errors, {$warnings->count()} warnings, {$info->count()} info");
        $this->command->line('');
    }
}