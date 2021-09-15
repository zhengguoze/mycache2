<?php

namespace AvailableCache\Engines;

use Illuminate\Contracts\Cache\Repository as Cache;

class SimpleCache
{
    const EXPIRE_INTERVAL_FIELD_NAME = 'expire_interval';

    /** @var Cache $cache */
    protected $cache;

    protected $key;
    protected $expireInterval;

    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @param $key
     * @param callable $callable
     * @param array $params
     * @param bool $overwrite
     * @return mixed
     */
    public function get($key, callable $callable, $params = [], $overwrite = false)
    {
        $this->initialize($key, $params);
        $data = $this->cache->get($this->key, false);
        if ($data === false || !empty($overwrite)) {
            $data = $callable();
            $this->cache->put($this->key, $data, $this->expireInterval / 60);
        }
        return $data;
    }

    /**
     * @param $key
     * @param $params
     */
    protected function initialize($key, $params)
    {
        $this->key = $key;
        $defaultParams = [
            static::EXPIRE_INTERVAL_FIELD_NAME => 600,
        ];
        $params = array_merge($defaultParams, $params);
        $this->expireInterval = $params[static::EXPIRE_INTERVAL_FIELD_NAME];
    }
}
