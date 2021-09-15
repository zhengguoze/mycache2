<?php

namespace AvailableCache\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Class AvailableCache
 * @package AvailableCache\Facades
 *
 * @method static mixed simple(string $key, callable $callable, $params = [], bool $overwrite = false)
 * @method static mixed l2(string $key, callable $callable, $params = [], bool $overwrite = false)
 *
 * @see \AvailableCache\AvailableCacheManager
 */
class AvailableCache extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'AvailableCache';
    }

}