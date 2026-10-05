<?php

use Mmstewart\LaravelXRay\Analyzers\BootstrapAnalyzer;

beforeEach(function () {
    $this->bootstrapPath = base_path('bootstrap/app.php');
    $this->backupPath = base_path('bootstrap/app.php.test-backup');

    copy($this->bootstrapPath, $this->backupPath);
});

afterEach(function () {
    rename($this->backupPath, $this->bootstrapPath);
});

it('reports a legacy bootstrap pattern', function () {
    file_put_contents(
        $this->bootstrapPath,
        <<<'PHP'
<?php

$app = new Illuminate\Foundation\Application(
    $_ENV['APP_BASE_PATH'] ?? dirname(__DIR__)
);

return $app;
PHP
    );

    $results = (new BootstrapAnalyzer([
        [
            'filename' => 'bootstrap/app.php',
            'patch' => '',
        ],
    ]))->analyze();

    expect($results)->toContain([
        'type' => 'bootstrap',
        'severity' => 'error',
        'message' => 'bootstrap/app.php is using the legacy Laravel bootstrap pattern. It needs to be migrated to Application::configure().',
        'key' => 'bootstrap/app.php',
    ]);
});

it('reports missing withRouting configuration', function () {
    file_put_contents(
        $this->bootstrapPath,
        <<<'PHP'
<?php

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withMiddleware()
    ->withExceptions()
    ->create();
PHP
    );

    $results = (new BootstrapAnalyzer([
        [
            'filename' => 'bootstrap/app.php',
            'patch' => "+    ->withRouting(\n",
        ],
    ]))->analyze();

    expect($results)->toContain([
        'type' => 'bootstrap',
        'severity' => 'warning',
        'message' => 'bootstrap/app.php is missing ->withRouting(). Laravel uses this to configure application routing.',
        'key' => 'withRouting',
    ]);
});

it('reports missing withMiddleware configuration', function () {
    file_put_contents(
        $this->bootstrapPath,
        <<<'PHP'
<?php

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting()
    ->withExceptions()
    ->create();
PHP
    );

    $results = (new BootstrapAnalyzer([
        [
            'filename' => 'bootstrap/app.php',
            'patch' => "+    ->withMiddleware(\n",
        ],
    ]))->analyze();

    expect($results)->toContain([
        'type' => 'bootstrap',
        'severity' => 'warning',
        'message' => 'bootstrap/app.php is missing ->withMiddleware(). Laravel uses this to configure middleware configuration.',
        'key' => 'withMiddleware',
    ]);
});

it('reports missing withExceptions configuration', function () {
    file_put_contents(
        $this->bootstrapPath,
        <<<'PHP'
<?php

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting()
    ->withMiddleware()
    ->create();
PHP
    );

    $results = (new BootstrapAnalyzer([
        [
            'filename' => 'bootstrap/app.php',
            'patch' => "+    ->withExceptions(\n",
        ],
    ]))->analyze();

    expect($results)->toContain([
        'type' => 'bootstrap',
        'severity' => 'warning',
        'message' => 'bootstrap/app.php is missing ->withExceptions(). Laravel uses this to configure exception handling.',
        'key' => 'withExceptions',
    ]);
});

it('reports no bootstrap issues when all critical methods are present', function () {
    file_put_contents(
        $this->bootstrapPath,
        <<<'PHP'
<?php

return Application::configure(
    basePath: dirname(__DIR__)
)
    ->withRouting()
    ->withMiddleware()
    ->withExceptions()
    ->create();
PHP
    );

    $results = (new BootstrapAnalyzer([
        [
            'filename' => 'bootstrap/app.php',
            'patch' => "+    ->withRouting(\n+    ->withMiddleware(\n+    ->withExceptions(\n",
        ],
    ]))->analyze();

    expect($results)->toBeEmpty();
});

it('returns no issues when no skeleton files are provided', function () {
    $results = (new BootstrapAnalyzer([]))->analyze();

    expect($results)->toBeEmpty();
});
