<?php

// config for Mmstewart/LaravelXRay
return [

    /*
    |--------------------------------------------------------------------------
    | GitHub Token
    |--------------------------------------------------------------------------
    |
    | Used to authenticate with the GitHub API when fetching Laravel skeleton
    | diffs. Without this you are limited to 60 requests per hour.
    |
    */

    'github_token' => env('GITHUB_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Valid Laravel Versions
    |--------------------------------------------------------------------------
    |
    | The Laravel versions X-Ray can compare between. Add new versions here
    | when Laravel releases a new major version without needing a package update.
    |
    */

    'valid_laravel_versions' => ['8.x', '9.x', '10.x', '11.x', '12.x', '13.x'],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | GitHub API responses are cached to avoid hitting rate limits. You can
    | disable caching or adjust the duration (in seconds) here.
    |
    */

    'cache' => [
        'enabled' => true,
        'duration' => 86400, // 24 hours — skeleton diffs never change after release
    ],

    /*
    |--------------------------------------------------------------------------
    | Analyzers
    |--------------------------------------------------------------------------
    |
    | Toggle individual analyzers on or off. Useful if you only care about
    | certain types of changes or want to speed up the scan.
    |
    */

    'analyzers' => [
        'env' => true,
        'composer' => true,
        'config' => true,
        'bootstrap' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Severity Threshold
    |--------------------------------------------------------------------------
    |
    | Only report issues at or above this severity level.
    |
    | Options: "info", "warning", "error"
    |
    */

    'minimum_severity' => 'info',

];
