# Laravel X-Ray

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mmstewart/laravel-x-ray.svg?style=flat-square)](https://packagist.org/packages/mmstewart/laravel-x-ray)
[![GitHub Tests Action Status](https://github.com/spatie/package-laravel-x-ray-laravel/actions/workflows/run-tests.yml/badge.svg)](https://github.com/mmstewart/laravel-x-ray/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/spatie/package-laravel-x-ray-laravel/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/mmstewart/laravel-x-ray/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/mmstewart/laravel-x-ray.svg?style=flat-square)](https://packagist.org/packages/mmstewart/laravel-x-ray)

Laravel X-Ray scans your codebase before a major version upgrade and produces
a prioritized report of incompatible packages, deprecated method usage, and
config drift. Before you change a single line of code.

No code leaves your machine, nothing gets changed. Run it locally or in CI
to know exactly what needs fixing before you upgrade.

## Installation

You can install the package via composer:

```bash
composer require mmstewart/laravel-x-ray
```

You can publish and run the migrations with:

```bash
php artisan vendor:publish --tag="laravel-x-ray-migrations"
php artisan migrate
```

You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-x-ray-config"
```

This is the contents of the published config file:

```php
return [
];
```

Optionally, you can publish the views using

```bash
php artisan vendor:publish --tag="laravel-x-ray-views"
```

## Usage

```php
$laravelXRay = new Mmstewart\LaravelXRay();
echo $laravelXRay->echoPhrase('Hello, Mmstewart!');
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [mmstewart](https://github.com/mmstewart)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
