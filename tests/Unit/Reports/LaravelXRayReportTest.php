<?php

use Illuminate\Console\Command;
use Mmstewart\LaravelXRay\Reports\LaravelXRayReport;

function reportCommand(): Command
{
    return Mockery::mock(Command::class);
}

it('reports errors that require attention before upgrading', function () {
    $command = reportCommand();

    $command->shouldReceive('line')
        ->atLeast()
        ->once();

    $command->shouldReceive('error')
        ->once()
        ->with('❌ 1 errors require attention before upgrading.');

    $report = new LaravelXRayReport($command);

    $report->display([
        [
            'type' => 'bootstrap',
            'severity' => 'error',
            'message' => 'Something needs attention.',
            'key' => 'bootstrap/app.php',
        ],
    ]);
});

it('reports warnings that should be reviewed before upgrading', function () {
    $command = reportCommand();

    $command->shouldReceive('line')
        ->atLeast()
        ->once();

    $command->shouldReceive('warn')
        ->once()
        ->with('⚠️  Review the warnings above before upgrading.');

    $report = new LaravelXRayReport($command);

    $report->display([
        [
            'type' => 'composer',
            'severity' => 'warning',
            'message' => 'Package requires adjustment.',
            'key' => 'package',
        ],
    ]);
});

it('reports when no issues are found', function () {
    $command = reportCommand();

    $command->shouldReceive('line')
        ->atLeast()
        ->once();

    $command->shouldReceive('info')
        ->once()
        ->with('✅ No issues found — you are ready to upgrade!');

    $report = new LaravelXRayReport($command);

    $report->display([]);
});

it('filters issues below the minimum severity', function () {
    config()->set('x-ray.minimum_severity', 'warning');

    $command = reportCommand();

    $command->shouldReceive('line')
        ->atLeast()
        ->once();

    $command->shouldReceive('warn')
        ->once()
        ->with('⚠️  Review the warnings above before upgrading.');

    $command->shouldNotReceive('info');

    $report = new LaravelXRayReport($command);

    $report->display([
        [
            'type' => 'env',
            'severity' => 'info',
            'message' => 'Missing env key.',
            'key' => 'APP_URL',
        ],
        [
            'type' => 'composer',
            'severity' => 'warning',
            'message' => 'Package requires adjustment.',
            'key' => 'package',
        ],
    ]);
});