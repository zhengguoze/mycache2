<?php

namespace AvailableCache;

use AvailableCache\Engines\L2AvailableCache;
use AvailableCache\Engines\SimpleCache;
use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Log\LoggerInterface;

class AvailableCacheManager
{
    /** @var Cache $cache */
    protected $cache;
    /** @var LoggerInterface $logger */
    protected $logger;

    public function __construct(Cache $cache, LoggerInterface $logger)
    {
        $this->cache = $cache;
        $this->logger = $logger;
    }

    /**
     * @param $key
     * @param callable $callable
     * @param array $params
     *      integer expire_interval 缓存有效时间（秒）
     * @param bool $overwrite
     * @return mixed
     */
    public function simple($key, callable $callable, $params = [], $overwrite = false)
    {
        $simpleCache = new SimpleCache($this->cache);
        return $simpleCache->get($key, $callable, $params, $overwrite);
    }

    /**
     * @param $key
     * @param callable $callable
     * @param array $params
     *      integer l1_expire_interval 一级缓存有效时间（秒）
     *      integer l2_expire_interval 二级缓存有效时间（秒）
     *      integer cache_lock_interval 生成数据锁有效时间（秒）
     *      callable alarm_callable 警告通知
     * @param bool $overwrite
     * @return mixed
     */
    public function l2($key, callable $callable, $params = [], $overwrite = false)
    {
        $l2AvailableCache = new L2AvailableCache($this->cache, $this->logger);
        return $l2AvailableCache->get($key, $callable, $params, $overwrite);
    }

}