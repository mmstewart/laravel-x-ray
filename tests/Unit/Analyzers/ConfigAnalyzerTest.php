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

it('reports a Postmark environment variable change', function () {
    $filename = 'config/services.php';

    $skeletonFiles = [
        [
            'filename' => $filename,
            'status' => 'modified',
            'patch' => <<<'PATCH'
-        'token' => env('POSTMARK_TOKEN'),
+        'key' => env('POSTMARK_API_KEY'),
PATCH,
        ],
    ];

    $results = (new ConfigAnalyzer($skeletonFiles, '13.x'))->analyze();

    expect($results)->toContain([
        'type' => 'config',
        'severity' => 'warning',
        'message' => "Laravel 13.x changed the Postmark environment variable from POSTMARK_TOKEN to POSTMARK_API_KEY. Update your .env configuration if you use Postmark.",
        'key' => $filename,
    ]);
});

it('reports a Resend environment variable change', function () {
    $filename = 'config/services.php';

    $skeletonFiles = [
        [
            'filename' => $filename,
            'status' => 'modified',
            'patch' => <<<'PATCH'
-        'key' => env('RESEND_KEY'),
+        'key' => env('RESEND_API_KEY'),
PATCH,
        ],
    ];

    $results = (new ConfigAnalyzer($skeletonFiles, '13.x'))->analyze();

    expect($results)->toContain([
        'type' => 'config',
        'severity' => 'warning',
        'message' => "Laravel 13.x changed the Resend environment variable from RESEND_KEY to RESEND_API_KEY. Update your .env configuration if you use Resend.",
        'key' => $filename,
    ]);
});

it('reports the logging retention configuration change', function () {
    $filename = 'config/logging.php';

    $skeletonFiles = [
        [
            'filename' => $filename,
            'status' => 'modified',
            'patch' => <<<'PATCH'
-            'days' => env('LOG_DAILY_DAYS', 14),
+            'max_files' => env('LOG_DAILY_DAYS', 14),
PATCH,
        ],
    ];

    $results = (new ConfigAnalyzer($skeletonFiles, '13.x'))->analyze();

    expect($results)->toContain([
        'type' => 'config',
        'severity' => 'warning',
        'message' => "Laravel 13.x changed the daily logging configuration from days to max_files. Review your log retention configuration.",
        'key' => $filename,
    ]);
});

it('reports the cache serializable classes setting', function () {
    $filename = 'config/cache.php';

    $skeletonFiles = [
        [
            'filename' => $filename,
            'status' => 'modified',
            'patch' => <<<'PATCH'
+    'serializable_classes' => false,
PATCH,
        ],
    ];

    $results = (new ConfigAnalyzer($skeletonFiles, '13.x'))->analyze();

    expect($results)->toContain([
        'type' => 'config',
        'severity' => 'info',
        'message' => 'Laravel 13.x introduced a cache serializable_classes setting. Review your cache configuration if your application stores serialized PHP objects in the cache.',
        'key' => $filename,
    ]);
});

it('reports the session serialization setting', function () {
    $filename = 'config/session.php';

    $skeletonFiles = [
        [
            'filename' => $filename,
            'status' => 'modified',
            'patch' => <<<'PATCH'
+    'serialization' => 'json',
PATCH,
        ],
    ];

    $results = (new ConfigAnalyzer($skeletonFiles, '13.x'))->analyze();

    expect($results)->toContain([
        'type' => 'config',
        'severity' => 'info',
        'message' => 'Laravel 13.x introduced a session serialization setting. Review your session configuration if your application stores PHP objects in sessions.',
        'key' => $filename,
    ]);
});

it('ignores modified config files without a known migration', function () {
    $filename = 'config/app.php';

    $skeletonFiles = [
        [
            'filename' => $filename,
            'status' => 'modified',
            'patch' => <<<'PATCH'
-'old' => true,
+'new' => true,
PATCH,
        ],
    ];

    $results = (new ConfigAnalyzer($skeletonFiles, '13.x'))->analyze();

    expect($results)->toBeEmpty();
});
