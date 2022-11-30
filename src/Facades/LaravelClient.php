<?php

namespace Vaharadev\LaravelClient\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Vaharadev\LaravelClient\LaravelClient
 */
class LaravelClient extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Vaharadev\LaravelClient\LaravelClient::class;
    }
}
