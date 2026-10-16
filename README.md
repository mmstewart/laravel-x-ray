# Laravel X-Ray

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mmstewart/laravel-x-ray.svg?style=flat-square)](https://packagist.org/packages/mmstewart/laravel-x-ray)
[![Total Downloads](https://img.shields.io/packagist/dt/mmstewart/laravel-x-ray.svg?style=flat-square)](https://packagist.org/packages/mmstewart/laravel-x-ray)
[![Run Tests](https://img.shields.io/github/actions/workflow/status/mmstewart/laravel-x-ray/run-tests.yml?style=flat-square&label=run-tests)](https://github.com/mmstewart/laravel-x-ray/actions/workflows/run-tests.yml)
[![PHP Version](https://img.shields.io/packagist/php-v/mmstewart/laravel-x-ray.svg?style=flat-square)](https://packagist.org/packages/mmstewart/laravel-x-ray)

Laravel X-Ray scans your Laravel application for potential compatibility issues before a Laravel version upgrade.

It compares relevant parts of your application against the target Laravel skeleton and analyzes dependencies, PHP requirements, bootstrap configuration, environment variables, and configuration files.

## Why Laravel X-Ray?

Laravel upgrades can involve more than changing the version of `laravel/framework`.

Dependencies may require newer versions, the Laravel application skeleton may change, and configuration or bootstrap files may need attention.

Laravel X-Ray gives you a report of potential issues before you begin the upgrade.

## Requirements

- PHP 8.4+
- Laravel 11, 12, or 13

## Installation

You can install the package via Composer:

```bash
composer require mmstewart/laravel-x-ray
```

## Usage

X-Ray accepts optional `from` and `to` arguments to specify the Laravel versions you want to compare.

### Compare specific Laravel versions

```bash
php artisan laravel-x-ray 11.x 13.x
```

The first argument (`11.x`) is the source Laravel version, and the second argument (`13.x`) is the target version you plan to upgrade to.

Version arguments accept either lowercase or uppercase `x`. For example, `11.x` and `11.X` are both valid.

### Automatic version detection

If you omit the arguments, X-Ray determines the source branch from your installed Laravel version and uses Laravel's default GitHub repository branch as the target:

```bash
php artisan laravel-x-ray
```

### Report formats

By default, X-Ray displays a human-readable table report. You can use the `--format` option to output findings as JSON for scripts and CI workflows.

**JSON output**

```bash
php artisan laravel-x-ray 11.x 13.x --format=json
```

The JSON report contains a summary with counts for errors, warnings, and informational findings, along with a findings array containing each finding's type, severity, message, and key.

Example JSON output:

```json
{
    "summary": {
        "errors": 0,
        "warnings": 1,
        "info": 0
    },
    "findings": [
        {
            "type": "composer",
            "severity": "warning",
            "message": "Example compatibility warning.",
            "key": "example/package"
        }
    ]
}
```

The example above is illustrative. Actual findings and severity counts depend on your application and the Laravel versions being compared.

Supported formats:

- table — Human-readable report (default).
- json — JSON report for machine-readable output.

```bash
# Explicitly use the default table format
php artisan laravel-x-ray 11.x 13.x --format=table
```

### Failure thresholds

Use the `--fail-on` option to make the command return a non-zero exit code when findings meet a specified severity threshold. This is useful for enforcing upgrade checks in CI workflows.

Supported thresholds:

- `error` — Fail if errors are found.
- `warning` — Fail if warnings or errors are found.
- `info` — Fail if any findings are found.

Examples:

Fail only when errors are found:

```bash
php artisan laravel-x-ray 11.x 13.x --fail-on=error
```

Fail when warnings or errors are found:

```bash
php artisan laravel-x-ray 11.x 13.x --fail-on=warning
```

Fail when any findings are found:

```bash
php artisan laravel-x-ray 11.x 13.x --fail-on=info
```

You can combine `--fail-on` with `--format=json` for machine-readable output in CI pipelines:

```bash
php artisan laravel-x-ray 11.x 13.x --format=json --fail-on=warning
```

By default, findings do not cause the command to fail. When `--fail-on` is specified, only findings that meet the configured `minimum_severity` setting are considered.

The command returns exit code `0` when the threshold is not met and exit code `1` when it is met. Invalid threshold values also return a non-zero exit code.

### Version validation

X-Ray validates the supplied version arguments before performing the comparison.

- **Valid formats:** `11.x`, `12.x`, `13.x`, `11.X`, `12.X`, and `13.X`.
- **Invalid formats:** `11x`, `12`, and `13x`.
- **Downgrade attempts:** A target version lower than the source version is rejected.
- **Same version:** If the source and target versions are identical, X-Ray reports that no newer Laravel skeleton branch is available.

### Example output

> **Note:** X-Ray identifies potential upgrade issues by comparing Laravel skeleton changes and dependency requirements. Review the findings in the context of your application; the report does not guarantee that an upgrade will succeed without additional changes.

Running `php artisan laravel-x-ray 11.x 13.x` produces a report similar to the following:

```text
Laravel 11.56.1
Comparing 11.x → 13.x

──────────────────────────────────────────────────
Laravel X-Ray Upgrade Report
──────────────────────────────────────────────────

⚠️ CHANGES TO REVIEW

  DEPENDENCY CHANGES
    • laravel/tinker (^2.9) is not compatible with Laravel 13.x. Recommended constraint: ^3.0.
    • spatie/laravel-permission (^5.7) is not compatible with Laravel 13.x. Recommended constraint: ^8.3.
    • laravel/pail (^1.1) is not compatible with Laravel 13.x. Recommended constraint: ^1.2.5.
    • laravel/pint (^1.13) is not compatible with Laravel 13.x. Recommended constraint: ^1.27.
    • nunomaduro/collision (^8.1) is not compatible with Laravel 13.x. Recommended constraint: ^8.6.
    • phpunit/phpunit (^11.0.1) is not compatible with Laravel 13.x. Recommended constraint: ^12.5.12.

  CONFIGURATION CHANGES
    • Laravel 13.x changed the daily logging configuration from days to max_files. Review your log retention configuration.
    • Laravel 13.x changed the Postmark environment variable from POSTMARK_TOKEN to POSTMARK_API_KEY. Update your .env configuration if you use Postmark.
    • Laravel 13.x changed the Resend environment variable from RESEND_KEY to RESEND_API_KEY. Update your .env configuration if you use Resend.

ℹ️ OPTIONAL REVIEW

  CONFIGURATION CHANGES
    • Laravel 13.x introduced a cache serializable_classes setting. Review your cache configuration if your application stores serialized PHP objects in the cache.
    • Laravel 13.x introduced a session serialization setting. Review your session configuration if your application stores PHP objects in sessions.

⚠️ Review the warnings above before upgrading.

──────────────────────────────────────────────────
0 errors, 9 warnings, 2 info
```

Use explicit versions when planning a specific upgrade, or omit the arguments to let X-Ray detect the versions automatically.

## What It Checks

### Composer dependencies

Laravel X-Ray checks your Composer dependencies against the target Laravel version.

It can identify:

- PHP version requirements
- Laravel framework dependencies
- Development dependencies
- Third-party packages with Laravel compatibility requirements
- Dependencies that may require a version change

When a package is incompatible, X-Ray checks its published version requirements and recommends the highest compatible stable version it finds for the target Laravel version.

Recommendations are based on declared Composer constraints and do not guarantee that a package will work with every application.

### Bootstrap configuration

Laravel X-Ray compares your `bootstrap/app.php` against the target Laravel application structure.

It can detect:

- Legacy Laravel bootstrap patterns
- Missing `withRouting()`
- Missing `withMiddleware()`
- Missing `withExceptions()`

### Environment variables

Laravel X-Ray compares your `.env.example` against the target Laravel skeleton and identifies newly introduced environment variables that are missing from your application.

> Laravel X-Ray intentionally checks `.env.example` rather than `.env`. Your `.env` may contain sensitive values and should not be committed or uploaded.

### Configuration files

Laravel X-Ray identifies configuration files that have been added, removed, or modified between Laravel versions.

It can detect:

- Configuration files added by the target Laravel version
- Configuration files removed by the target Laravel version
- Known configuration migrations that may require changes to your application
- Known environment variable changes within Laravel service configuration

X-Ray intentionally does not report every modified configuration file. It focuses on known Laravel changes that provide an actionable upgrade warning or informational finding.

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag="laravel-x-ray-config"
```

The configuration allows you to control features such as caching, analyzers, GitHub authentication, and minimum severity.

Example:

```php
return [

    'github_token' => env('GITHUB_TOKEN'),

    'cache' => [
        'enabled' => true,
        'skeleton' => 60 * 24 * 7,
        'default_branch' => 60 * 24,
        'packagist' => 60 * 2,
    ],

    'analyzers' => [
        'env' => true,
        'composer' => true,
        'config' => true,
        'bootstrap' => true,
    ],

    'minimum_severity' => 'info',

];
```

### GitHub Token

A GitHub token is optional. Laravel X-Ray can use the GitHub API without one, but authenticated requests have a higher API rate limit.

If you want to provide a token, add `GITHUB_TOKEN` to your application's `.env` file:

```env
GITHUB_TOKEN=your-token
```

You do not need to configure a token for X-Ray to work.

### Caching

Laravel X-Ray caches Laravel skeleton and Packagist data to avoid unnecessary API requests.

Caching can be disabled in the configuration:

```php
'cache' => [
    'enabled' => false,
],
```

### Severity Levels

Findings are grouped by severity:

| Severity | Meaning                                                                          |
| -------- | -------------------------------------------------------------------------------- |
| Error    | A compatibility issue that may prevent the application from working correctly    |
| Warning  | An issue that should be addressed during the upgrade                             |
| Info     | Something that should be reviewed but is not necessarily a compatibility problem |

You can configure the minimum severity reported by X-Ray.

```php
'minimum_severity' => 'info',
```

Available levels:

```text
info
warning
error
```

## Testing

Run the test suite with:

```bash
composer test
```

## Changelog

See [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review the [Security Policy](SECURITY.md) for information on reporting vulnerabilities.

## Credits

- [mmstewart](https://github.com/mmstewart)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
