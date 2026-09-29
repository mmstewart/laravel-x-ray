<?php

use Mmstewart\LaravelXRay\Analyzers\ConfigAnalyzer;

it('reports an added config file that does not exist', function () {
    $filename = 'config/x-ray-test.php';

    if (file_exists(base_path($filename))) {
        unlink(base_path($filename));
    }

    $skeletonFiles = [
        [
            'filename' => $filename,
            'status' => 'added',
        ],
    ];

    $results = (new ConfigAnalyzer($skeletonFiles, '13.x'))->analyze();

    expect($results)->toContain([
        'type' => 'config',
        'severity' => 'info',
        'message' => "Config file {$filename} was added to the Laravel 13.x skeleton. Review whether it is needed.",
        'key' => $filename,
    ]);
});

it('does not report an added config file that already exists', function () {
    $filename = 'config/x-ray-test.php';

    file_put_contents(base_path($filename), "<?php\nreturn [];\n");

    $skeletonFiles = [
        [
            'filename' => $filename,
            'status' => 'added',
        ],
    ];

    $results = (new ConfigAnalyzer($skeletonFiles, '13.x'))->analyze();

    unlink(base_path($filename));

    expect($results)->toBeEmpty();
});

it('reports a removed config file that still exists', function () {
    $filename = 'config/x-ray-test.php';

    file_put_contents(base_path($filename), "<?php\nreturn [];\n");

    $skeletonFiles = [
        [
            'filename' => $filename,
            'status' => 'removed',
        ],
    ];

    $results = (new ConfigAnalyzer($skeletonFiles, '13.x'))->analyze();

    unlink(base_path($filename));

    expect($results)->toContain([
        'type' => 'config',
        'severity' => 'info',
        'message' => "Config file {$filename} was removed from the Laravel 13.x skeleton. Review whether it is still needed.",
        'key' => $filename,
    ]);
});

it('does not report a removed config file that does not exist', function () {
    $filename = 'config/x-ray-test.php';

    if (file_exists(base_path($filename))) {
        unlink(base_path($filename));
    }

    $skeletonFiles = [
        [
            'filename' => $filename,
            'status' => 'removed',
        ],
    ];

    $results = (new ConfigAnalyzer($skeletonFiles, '13.x'))->analyze();

    expect($results)->toBeEmpty();
});

it('reports a modified config file', function () {
    $filename = 'config/x-ray-test.php';

    file_put_contents(base_path($filename), "<?php\nreturn [];\n");

    $skeletonFiles = [
        [
            'filename' => $filename,
            'status' => 'modified',
        ],
    ];

    $results = (new ConfigAnalyzer($skeletonFiles, '13.x'))->analyze();

    unlink(base_path($filename));

    expect($results)->toContain([
        'type' => 'config',
        'severity' => 'info',
        'message' => "Config file {$filename} was modified in the Laravel 13.x skeleton. Review your configuration for changes.",
        'key' => $filename,
    ]);
});

it('ignores non-config files', function () {
    $skeletonFiles = [
        [
            'filename' => 'routes/web.php',
            'status' => 'modified',
        ],
    ];

    $results = (new ConfigAnalyzer($skeletonFiles, '13.x'))->analyze();

    expect($results)->toBeEmpty();
});
