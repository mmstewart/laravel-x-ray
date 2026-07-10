<?php

namespace Mmstewart\LaravelXRay\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Mmstewart\LaravelXRay\LaravelXRay
 */
class LaravelXRay extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Mmstewart\LaravelXRay\LaravelXRay::class;
    }
}
