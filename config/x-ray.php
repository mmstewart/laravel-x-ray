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
    | Cache
    |--------------------------------------------------------------------------
    |
    | GitHub API responses are cached to avoid hitting rate limits. You can
    | disable caching or adjust the duration (in minutes) here.
    |
    */

    'cache' => [
        'enabled' => true,
        'skeleton' => 60 * 24 * 7,  // 7 days
        'default_branch' => 60 * 24,  // 1 day
        'packagist' => 60 * 2,  // 2 hours
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
