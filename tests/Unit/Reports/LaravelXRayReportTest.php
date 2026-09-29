<?php

use Illuminate\Console\Command;
use Mmstewart\LaravelXRay\Reports\LaravelXRayReport;
use Symfony\Component\Console\Output\BufferedOutput;

function reportCommand(): array
{
    $output = new BufferedOutput;

    $command = new class($output) extends Command
    {
        public function __construct(
            private BufferedOutput $buffer
        ) {
            parent::__construct();
        }

        public function line($string, $style = null, $verbosity = null)
        {
            $this->buffer->writeln($string);

            return $this;
        }

        public function info($string, $verbosity = null)
        {
            $this->buffer->writeln($string);

            return $this;
        }
    };

    return [$command, $output];
}

it('shows info, warnings, and errors when minimum severity is info', function () {
    config(['x-ray.minimum_severity' => 'info']);

    [$command, $output] = reportCommand();

    $report = new LaravelXRayReport($command);

    $report->display([
        ['severity' => 'info', 'message' => 'Info issue'],
        ['severity' => 'warning', 'message' => 'Warning issue'],
        ['severity' => 'error', 'message' => 'Error issue'],
    ]);

    expect($output->fetch())
        ->toContain('Info issue')
        ->toContain('Warning issue')
        ->toContain('Error issue');
});

it('hides info when minimum severity is warning', function () {
    config(['x-ray.minimum_severity' => 'warning']);

    [$command, $output] = reportCommand();

    $report = new LaravelXRayReport($command);

    $report->display([
        ['severity' => 'info', 'message' => 'Info issue'],
        ['severity' => 'warning', 'message' => 'Warning issue'],
        ['severity' => 'error', 'message' => 'Error issue'],
    ]);

    expect($output->fetch())
        ->not->toContain('Info issue')
        ->toContain('Warning issue')
        ->toContain('Error issue');
});

it('only shows errors when minimum severity is error', function () {
    config(['x-ray.minimum_severity' => 'error']);

    [$command, $output] = reportCommand();

    $report = new LaravelXRayReport($command);

    $report->display([
        ['severity' => 'info', 'message' => 'Info issue'],
        ['severity' => 'warning', 'message' => 'Warning issue'],
        ['severity' => 'error', 'message' => 'Error issue'],
    ]);

    expect($output->fetch())
        ->not->toContain('Info issue')
        ->not->toContain('Warning issue')
        ->toContain('Error issue');
});

it('displays no issues when the result set is empty', function () {
    config(['x-ray.minimum_severity' => 'info']);

    [$command, $output] = reportCommand();

    $report = new LaravelXRayReport($command);

    $report->display([]);

    expect($output->fetch())
        ->toContain('No issues found');
});
