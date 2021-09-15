<?php

namespace AvailableCache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AvailableCacheServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton('AvailableCache', function ($app) {
            return new AvailableCacheManager(Cache::store(), Log::getFacadeRoot());
        });
    }
}