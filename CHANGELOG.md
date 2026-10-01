# Changelog

All notable changes to `laravel-x-ray` will be documented in this file.

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