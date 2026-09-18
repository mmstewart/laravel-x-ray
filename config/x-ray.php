<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GitHub Token
    |--------------------------------------------------------------------------
    |
    | Optional. Used to authenticate with the GitHub API when fetching Laravel skeleton
    | information. Providing a token increases the available API rate limit.
    |
    */

    'github_token' => env('GITHUB_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | API responses are cached to reduce network requests and avoid hitting
    | API rate limits. You can disable caching or adjust the duration in
    | minutes here.
    |
    */

    'cache' => [
        'enabled' => true,
        'default_branch' => 60 * 24,  // 1 day
        'packagist' => 60 * 2,        // 2 hours
        'skeleton' => 60 * 24 * 7,    // 7 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Analyzers
    |--------------------------------------------------------------------------
    |
    | Enable or disable individual compatibility checks.
    |
    */

    'analyzers' => [
        'bootstrap' => true,
        'composer' => true,
        'config' => true,
        'env' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Minimum Severity
    |--------------------------------------------------------------------------
    |
    | Only report issues at or above this severity level.
    |
    | Levels, from least to most severe:
    | info, warning, error
    |
    */

    'minimum_severity' => 'info',

];
