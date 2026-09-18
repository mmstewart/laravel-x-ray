# Laravel X-Ray

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mmstewart/laravel-x-ray.svg?style=flat-square)](https://packagist.org/packages/mmstewart/laravel-x-ray)
[![GitHub Tests Action Status](https://github.com/spatie/package-laravel-x-ray-laravel/actions/workflows/run-tests.yml/badge.svg)](https://github.com/mmstewart/laravel-x-ray/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/spatie/package-laravel-x-ray-laravel/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/mmstewart/laravel-x-ray/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mmstewart/laravel-x-ray.svg?style=flat-square)](https://packagist.org/packages/mmstewart/laravel-x-ray)

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

To explicitly compare your current Laravel version against a target version:

```bash
php artisan laravel-x-ray 11.x 13.x
```

The first argument (`11.x`) is the Laravel version your application is currently using, and the second argument (`13.x`) is the version you are planning to upgrade to.

If you omit the arguments, X-Ray determines the source branch from your installed Laravel version and uses Laravel's default GitHub repository branch as the target:

```bash
php artisan laravel-x-ray
```

You can use explicit versions when planning a specific upgrade, or run X-Ray without arguments when you want it to use the default branch configuration.

Example output:

```text
Laravel 11.56.1
Comparing 11.x → 13.x

──────────────────────────────────────────────────
Laravel X-Ray Upgrade Report
──────────────────────────────────────────────────

⚠️ WARNINGS (6)
  • laravel/tinker (^2.9) requires adjustment for Laravel 13.x compatibility. Recommended constraint: ^3.0.
  • spatie/laravel-permission (^5.7) requires adjustment for Laravel 13.x compatibility. Recommended version: ^8.3.0.
  • laravel/pail (^1.1) requires adjustment for Laravel 13.x compatibility. Recommended constraint: ^1.2.5.
  • laravel/pint (^1.13) requires adjustment for Laravel 13.x compatibility. Recommended constraint: ^1.27.
  • nunomaduro/collision (^8.1) requires adjustment for Laravel 13.x compatibility. Recommended constraint: ^8.6.
  • phpunit/phpunit (^11.0.1) requires adjustment for Laravel 13.x compatibility. Recommended constraint: ^12.5.12.

ℹ️ INFO (10)
  • Config file config/app.php was modified in the Laravel 13.x skeleton. Review your configuration for changes.
  • Config file config/auth.php was modified in the Laravel 13.x skeleton. Review your configuration for changes.
...

──────────────────────────────────────────────────
0 errors, 6 warnings, 10 info
```

## What It Checks

### Composer dependencies

Laravel X-Ray checks your Composer dependencies against the target Laravel version.

It can identify:

- PHP version requirements
- Laravel framework dependencies
- Development dependencies
- Third-party packages with Laravel compatibility requirements
- Dependencies that may require a version change

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

Modified configuration files are flagged for review during the upgrade.

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
