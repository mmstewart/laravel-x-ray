<?php

use Mmstewart\LaravelXRay\Analyzers\EnvAnalyzer;

beforeEach(function () {
    $this->envExamplePath = base_path('.env.example');
    $this->backupPath = base_path('.env.example.test-backup');

    if (file_exists($this->envExamplePath)) {
        copy($this->envExamplePath, $this->backupPath);
    }
});

afterEach(function () {
    if (file_exists($this->backupPath)) {
        rename($this->backupPath, $this->envExamplePath);
    } elseif (file_exists($this->envExamplePath)) {
        unlink($this->envExamplePath);
    }
});

it('reports an environment variable missing from the application', function () {
    file_put_contents(
        $this->envExamplePath,
        "APP_NAME=Laravel\n"
    );

    $skeletonFiles = [
        [
            'filename' => '.env.example',
            'patch' => "+APP_NAME=Laravel\n+APP_URL=http://localhost\n",
        ],
    ];

    $results = (new EnvAnalyzer($skeletonFiles))->analyze();

    expect($results)->toContain([
        'type' => 'env',
        'severity' => 'info',
        'message' => 'Missing env key: APP_URL',
        'key' => 'APP_URL',
    ]);
});

it('does not report an environment variable that already exists', function () {
    file_put_contents(
        $this->envExamplePath,
        "APP_NAME=Laravel\nAPP_URL=http://localhost\n"
    );

    $skeletonFiles = [
        [
            'filename' => '.env.example',
            'patch' => "+APP_NAME=Laravel\n+APP_URL=http://localhost\n",
        ],
    ];

    $results = (new EnvAnalyzer($skeletonFiles))->analyze();

    expect($results)->toBeEmpty();
});

it('ignores commented environment variables', function () {
    file_put_contents(
        $this->envExamplePath,
        "APP_NAME=Laravel\n"
    );

    $skeletonFiles = [
        [
            'filename' => '.env.example',
            'patch' => "+APP_NAME=Laravel\n+# NEW_VARIABLE=value\n",
        ],
    ];

    $results = (new EnvAnalyzer($skeletonFiles))->analyze();

    expect($results)->toBeEmpty();
});

it('ignores diff metadata lines', function () {
    file_put_contents(
        $this->envExamplePath,
        "APP_NAME=Laravel\n"
    );

    $skeletonFiles = [
        [
            'filename' => '.env.example',
            'patch' => "+++ b/.env.example\n",
        ],
    ];

    $results = (new EnvAnalyzer($skeletonFiles))->analyze();

    expect($results)->toBeEmpty();
});

it('returns no issues when the skeleton has no environment patch', function () {
    $results = (new EnvAnalyzer([]))->analyze();

    expect($results)->toBeEmpty();
});

it('does not crash when the application has no env example file', function () {
    if (file_exists($this->envExamplePath)) {
        unlink($this->envExamplePath);
    }

    $skeletonFiles = [
        [
            'filename' => '.env.example',
            'patch' => "+APP_URL=http://localhost\n",
        ],
    ];

    $results = (new EnvAnalyzer($skeletonFiles))->analyze();

    expect($results)->toContain([
        'type' => 'env',
        'severity' => 'info',
        'message' => 'Missing env key: APP_URL',
        'key' => 'APP_URL',
    ]);
});

it('ignores removed environment variables', function () {
    file_put_contents(
        $this->envExamplePath,
        "APP_NAME=Laravel\n"
    );

    $skeletonFiles = [
        [
            'filename' => '.env.example',
            'patch' => "-APP_DEBUG=true\n",
        ],
    ];

    $results = (new EnvAnalyzer($skeletonFiles))->analyze();

    expect($results)->toBeEmpty();
});
