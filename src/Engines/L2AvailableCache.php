<?php

namespace AvailableCache\Engines;

use Illuminate\Contracts\Cache\Repository as Cache;
use Psr\Log\LoggerInterface;
use LogicException;
use DateTime;
use Exception;

class L2AvailableCache
{
    const GENERATE_LOCK_SUFFIX = '_generate_lock';
    const L1_CACHE_KEY_SUFFIX = '_l1_cache';
    const L2_CACHE_KEY_SUFFIX = '_l2_cache';
    const ALARM_LOCK_SUFFIX = '_alarm_lock';

    const EXPIRE_TIME_FIELD_NAME = 'expire_time';
    const ORIGIN_DATA_FIELD_NAME = 'origin_data';

    const L1_EXPIRE_INTERVAL_FIELD_NAME = 'l1_expire_interval';
    const L2_EXPIRE_INTERVAL_FIELD_NAME = 'l2_expire_interval';
    const CACHE_LOCK_INTERVAL_FIELD_NAME = 'cache_lock_interval';
    const ALARM_CALLABLE_FIELD_NAME = 'alarm_callable';
    const PROBE_FIELD_NAME = 'probe';

    const L2_EXPIRE_INTERVAL_REMAIN_INTERVAL = 3600;
    const MIN_L1_AND_L2_EXPIRE_INTERVAL_DIFF = 3600;
    const ALARM_LOCK_INTERVAL = 600;

    protected $id;
    protected $l1Key;
    protected $l2Key;
    protected $generateLockKey;
    protected $alarmLockKey;
    protected $l1ExpireInterval;
    protected $l2ExpireInterval;
    protected $cacheLockInterval;
    protected $alarmLockInterval;
    /** @var callable $alarmCallable */
    protected $alarmCallable;
    /** @var boolean $probe */
    protected $probe;

    /** @var Cache $cache */
    protected $cache;
    /** @var LoggerInterface $logger */
    protected $logger;
    /** @var DateTime|null $datetime */
    protected $datetime;

    public function __construct(Cache $cache, LoggerInterface $logger, DateTime $dateTime = null)
    {
        $this->cache = $cache;
        $this->logger = $logger;
        $this->datetime = !empty($dateTime) ? $dateTime : new DateTime();
    }

    /**
     * @param $key
     * @param callable $callable
     * @param array $params
     *      integer l1_expire_interval 一级缓存有效时间（秒）
     *      integer l2_expire_interval 二级缓存有效时间（秒）
     *      integer cache_lock_interval 生成数据锁有效时间（秒）
     *      callable alarm_callable 警告通知
     *      debug 是否打开调试日志
     * @param bool $overwrite
     * @return mixed
     */
    public function get($key, callable $callable, $params = [], $overwrite = false)
    {
        $this->initialize($key, $params);
        $this->validate();

        $data = $this->getCacheData($this->l1Key,false);
        if ($data === false || !empty($overwrite)) {
            $data = $this->generateCacheData($callable);
        }

        return $this->getOriginData($data);
    }

    /**
     * @param $key
     * @param $params
     */
    protected function initialize($key, $params)
    {
        $this->l1Key = $this->generateKey($key, '', static::L1_CACHE_KEY_SUFFIX);
        $this->l2Key = $this->generateKey($key, '', static::L2_CACHE_KEY_SUFFIX);
        $this->generateLockKey = $this->generateKey($key, '', static::GENERATE_LOCK_SUFFIX);
        $this->alarmLockKey = $this->generateKey($key, '', static::ALARM_LOCK_SUFFIX);
        $defaultParams = [
            static::L1_EXPIRE_INTERVAL_FIELD_NAME => 600,
            static::L2_EXPIRE_INTERVAL_FIELD_NAME => 86400,
            static::CACHE_LOCK_INTERVAL_FIELD_NAME => 300,
            static::ALARM_CALLABLE_FIELD_NAME => null,
            static::PROBE_FIELD_NAME => false,
        ];
        $params = array_merge($defaultParams, $params);
        $this->l1ExpireInterval = $params[static::L1_EXPIRE_INTERVAL_FIELD_NAME];
        $this->l2ExpireInterval = $params[static::L2_EXPIRE_INTERVAL_FIELD_NAME];
        $this->cacheLockInterval = $params[static::CACHE_LOCK_INTERVAL_FIELD_NAME];
        $this->alarmLockInterval = static::ALARM_LOCK_INTERVAL;
        $this->alarmCallable = $params[static::ALARM_CALLABLE_FIELD_NAME];
        $this->probe = $params[static::PROBE_FIELD_NAME];
        $this->id = $this->generateId();

        $this->probe('initialize');
    }

    /**
     * 生成ID
     * @return string
     */
    protected function generateId()
    {
        return date('YmdHis') . mt_rand(100000, 999999);
    }

    /**
     * 验证相关参数
     */
    protected function validate()
    {
        if ($this->l1ExpireInterval + static::MIN_L1_AND_L2_EXPIRE_INTERVAL_DIFF > $this->l2ExpireInterval) {
            throw new LogicException(sprintf('二级缓存有效时间必须大于一级缓存有效时间%s秒', static::MIN_L1_AND_L2_EXPIRE_INTERVAL_DIFF), 2000);
        }
    }

    /**
     * @param $key
     * @param string $prefix
     * @param string $suffix
     * @return string
     */
    protected function generateKey($key, $prefix = '', $suffix = '')
    {
        return sprintf('%s%s%s', $prefix, $key, $suffix);
    }

    /**
     * 生成缓存
     * @param callable $callable
     * @return array|bool
     */
    protected function generateCacheData(callable $callable)
    {
        if ($this->addGenerateLock()) {
            try {
                $originData = $callable();
                $data = $this->cacheData($this->l1Key, $originData, $this->l1ExpireInterval);
                $this->cacheData($this->l2Key, $originData, $this->l2ExpireInterval);
            } catch (Exception $e) {
                $this->log('error', $e->getMessage());
                $this->sendingAlarms($e->getMessage());
                $data = $this->getCacheData($this->l2Key,false);
            } finally {
                $this->clearGenerateLock();
            }
        } else {
            $data = $this->getCacheData($this->l2Key,false);
            if ($data === false || $this->checkExpireIntervalRemainInterval($this->getExpireTime($data))) {
                $this->sendingAlarms('二级缓存有效时间小于阈值，请尽快处理');
            }
        }
        return $data;
    }

    /**
     * @param $message
     * @return bool
     */
    protected function sendingAlarms($message)
    {
        if (!empty($this->alarmCallable) && $this->addAlarmLock()) {
            try {
                call_user_func_array($this->alarmCallable, [$message]);
                $this->probe('sending_alarms', [
                    'message' => $message
                ]);
                return true;
            } catch (Exception $e) {
                $this->log('error', $e->getMessage());
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    protected function addAlarmLock()
    {
        $result = $this->cache->add($this->alarmLockKey, 1, $this->alarmLockInterval / 60);
        $this->probe('add_alarm_lock', ['result' => $result]);
        return $result;
    }

    /**
     * @param $key
     * @param $originData
     * @param $interval
     * @return array
     */
    protected function cacheData($key, $originData, $interval)
    {
        $data = $this->packageData($originData, $interval);
        $this->probe('set_cache_data', [
            'key' => $key,
            'data' => $data,
            'interval' => $interval
        ]);
        $this->cache->put($key, $data, $interval / 60);
        return $data;
    }

    /**
     * 获取缓存
     * @param $key
     * @param $default
     * @return mixed
     */
    protected function getCacheData($key, $default) {
        $data = $this->cache->get($key, $default);
        $this->probe('get_cache_data', [
            'key' => $key,
            'default' => $default,
            'data' => $data
        ]);
        return $data;
    }

    /**
     * 增加生成缓存锁
     * @return bool
     */
    protected function addGenerateLock()
    {
        $result = $this->cache->add($this->generateLockKey, 1, $this->cacheLockInterval / 60);
        $this->probe('add_generate_lock', ['result' => $result]);
        return $result;
    }

    /**
     * 释放生成缓存锁
     * @return bool
     */
    protected function clearGenerateLock()
    {
        $result = $this->cache->forget($this->generateLockKey);
        $this->probe('clear_generate_lock', ['result' => $result]);
        return $result;
    }

    /**
     * 组装缓存数据
     * @param $originData
     * @param $expireInterval
     * @return array
     */
    protected function packageData($originData, $expireInterval)
    {
        return [
            static::ORIGIN_DATA_FIELD_NAME => $originData,
            static::EXPIRE_TIME_FIELD_NAME => !empty($expireInterval) ? $this->getTimestamp() + $expireInterval : 0,
        ];
    }

    /**
     * 获取原始数据
     * @param $data
     * @return mixed|null
     */
    protected function getOriginData($data)
    {
        return $data[static::ORIGIN_DATA_FIELD_NAME] ?? false;
    }

    /**
     * @param $data
     * @return mixed|null
     */
    protected function getExpireTime($data)
    {
        return $data[static::EXPIRE_TIME_FIELD_NAME] ?? false;
    }

    /**
     * 检查临近过期时间
     * @param $expireTime
     * @return bool
     */
    protected function checkExpireIntervalRemainInterval($expireTime)
    {
        if (!empty($expireTime)) {
            $expireInterval = $expireTime - $this->getTimestamp();
            if (static::L2_EXPIRE_INTERVAL_REMAIN_INTERVAL > 0 && $expireInterval < static::L2_EXPIRE_INTERVAL_REMAIN_INTERVAL) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return int
     */
    protected function getTimestamp()
    {
        return $this->datetime->getTimestamp();
    }

    /**
     * @param $level
     * @param $message
     * @param array $context
     */
    protected function log($level, $message, $context = [])
    {
        $this->logger->log($level, $message, $context);
    }

    /**
     * 记录调试数据
     * @param $type
     * @param array $context
     * @return bool
     */
    protected function probe($type, $context = [])
    {
        if ($this->probe) {
            switch ($type) {
                case 'initialize':
                    $this->log('debug', $type, $this->snapshot());
                    break;
                default:
                    $context = array_merge($context, ['id' => $this->id]);
                    $this->log('debug', $type, $context);
            }
        }
        return true;
    }

    /**
     * @return mixed
     */
    protected function snapshot()
    {
        $fields = [
            'id', 'l1Key', 'l2Key', 'generateLockKey', 'alarmLockKey', 'l1ExpireInterval', 'l2ExpireInterval',
            'cacheLockInterval', 'alarmLockInterval', 'alarmCallable', 'probe'
        ];
        $data = [];
        foreach ($fields as $field) {
            $data[$field] = $this->{$field};
        }
        return $data;
    }
}
