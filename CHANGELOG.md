# Changelog

All notable changes to `laravel-x-ray` will be documented in this file.

## Laravel X-Ray v1.1.0 🎉 - 2026-10-05

### Improvements

Laravel X-Ray v1.1.0 improves upgrade analysis by focusing on actionable Laravel changes and providing more useful compatibility reports.

#### Configuration Analysis

- Detect known Laravel configuration changes between versions
- Detect the `POSTMARK_TOKEN` to `POSTMARK_API_KEY` environment variable change
- Detect the `RESEND_KEY` to `RESEND_API_KEY` environment variable change
- Detect the Laravel daily logging configuration change from `days` to `max_files`
- Detect the `cache.serializable_classes` configuration setting
- Detect the session `serialization` configuration setting
- Focus on known, actionable configuration changes instead of reporting every modified configuration file

#### Environment Analysis

- Improve `.env.example` analysis to identify newly introduced environment variables
- Ignore removed environment variables
- Improve handling of comments and Git diff metadata

#### Bootstrap Analysis

- Detect legacy Laravel bootstrap patterns
- Detect missing `withRouting()` configuration
- Detect missing `withMiddleware()` configuration
- Detect missing `withExceptions()` configuration
- Improve bootstrap analysis by locating `bootstrap/app.php` directly in the Laravel skeleton comparison
- Provide more descriptive messages for bootstrap configuration issues

#### Reporting

- Improve severity-based compatibility reporting
- Display separate error, warning, and informational sections
- Add clearer status messages based on the highest severity found
- Respect the configured `minimum_severity` when displaying findings

#### Testing

- Add tests for known Laravel configuration migrations
- Add tests for environment variable additions and removals
- Add tests for Laravel bootstrap configuration changes
- Add tests for report severity filtering and status messages

## Laravel X-Ray v1.0.1 🎉 - 2026-10-01

### Initial Release

Laravel X-Ray is a Laravel upgrade compatibility scanner that analyzes your application for potential issues before upgrading Laravel.

#### Features

- Check Laravel upgrade compatibility
- Compare Composer dependencies against the target Laravel version
- Detect PHP version compatibility issues
- Check development dependencies
- Analyze Laravel configuration files
- Analyze `.env.example` changes
- Check Laravel bootstrap configuration
- Generate severity-based compatibility reports

#### Compatibility

- PHP 8.4+
- Laravel 11.x
- Laravel 12.x
- Laravel 13.x

#### Installation

```bash
composer require mmstewart/laravel-x-ray
```
