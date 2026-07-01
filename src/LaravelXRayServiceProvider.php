<?php

namespace Mmstewart\LaravelXRay;

use Mmstewart\LaravelXRay\Commands\LaravelXRayCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelXRayServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        // /*
        //  * This class is a Package Service Provider
        //  *
        //  * More info: https://github.com/spatie/laravel-package-tools
        //  */
        $package
            ->name('laravel-x-ray')
            ->hasConfigFile()
            ->hasViews()
            ->hasCommand(LaravelXRayCommand::class);
    }
}
