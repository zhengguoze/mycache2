<?php
/**
 * Created by PhpStorm.
 * User: Luwei
 * Date: 2019/11/4
 * Time: 14:51
 */

namespace AvailableCache\Tests\Engines;

use AvailableCache\Engines\L2AvailableCache;
use AvailableCache\Tests\TestCase;
use Illuminate\Cache\Repository;
use Illuminate\Log\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use DateTime;
use Exception;

class L2AvailableCacheTest extends TestCase
{
    /** @var int $nowTimestamp */
    protected $nowTimestamp = 10000000;

    public function dpGetSceneHasL1CacheAssertCacheValue()
    {
        return [
            ['key1', 'functionValue1', 'l1CacheValue1'],
            ['key2', 'functionValue2', 'l1CacheValue2'],
            ['key3', 'functionValue3', 'l1CacheValue3'],
        ];
    }

    /**
     * 环境：
     * 一级缓存存在
     * @dataProvider dpGetSceneHasL1CacheAssertCacheValue
     * @param $key
     * @param $value
     * @param $excepted
     * @throws Exception
     */
    public function testGetSceneHasL1CacheAssertCacheValue($key, $value, $excepted)
    {
        $datetime = new DateTime();
        $datetime->setTimestamp($this->nowTimestamp);
        /** @var Logger|MockObject $loggerStub */
        $loggerStub = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['error', 'debug'])
            ->getMock();
        /** @var Repository|MockObject $cacheStub */
        $cacheStub = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'get', 'put', 'forget'])
            ->getMock();
        $cacheStub->method('get')->willReturnMap([
            ['key1_l1_cache', false, [
                'origin_data' => 'l1CacheValue1',
                'expire_time' => $this->nowTimestamp + 600,
            ]],
            ['key2_l1_cache', false, [
                'origin_data' => 'l1CacheValue2',
                'expire_time' => $this->nowTimestamp + 600,
            ]],
            ['key3_l1_cache', false, [
                'origin_data' => 'l1CacheValue3',
                'expire_time' => $this->nowTimestamp + 600,
            ]],
        ]);
        $l2AvailableCache = new L2AvailableCache($cacheStub, $loggerStub, $datetime);
        $actual = $l2AvailableCache->get($key, function () use ($value) {
            return $value;
        });
        $this->assertEquals($excepted, $actual);
    }

    public function dpGetSceneNoL1CacheAndHasL2CacheAssertCacheValue()
    {
        return [
            ['key1', 'functionValue1', 'l2CacheValue1'],
            ['key2', 'functionValue2', 'l2CacheValue2'],
            ['key3', 'functionValue3', 'l2CacheValue3'],
        ];
    }

    /**
     * 环境：
     * 一级缓存不存在
     * 二级缓存存在
     * 缓存生成锁已存在
     * 二级缓存过期时间大于警戒时间
     * @dataProvider dpGetSceneNoL1CacheAndHasL2CacheAssertCacheValue
     * @param $key
     * @param $value
     * @param $excepted
     * @throws Exception
     */
    public function testGetSceneNoL1CacheAndHasL2CacheAndHasGenerateLockAndL2CacheIntervalGreaterThanAssertCacheValue($key, $value, $excepted)
    {
        $datetime = new DateTime();
        $datetime->setTimestamp($this->nowTimestamp);
        /** @var Logger|MockObject $loggerStub */
        $loggerStub = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['error', 'info'])
            ->getMock();
        /** @var Repository|MockObject $cacheStub */
        $cacheStub = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'get', 'put', 'forget'])
            ->getMock();
        $cacheStub->method('get')->willReturnMap([
            ['key1_l1_cache', false, false],
            ['key2_l1_cache', false, false],
            ['key3_l1_cache', false, false],
            ['key1_l2_cache', false, [
                'origin_data' => 'l2CacheValue1',
                'expire_time' => $this->nowTimestamp + 7200,
            ]],
            ['key2_l2_cache', false, [
                'origin_data' => 'l2CacheValue2',
                'expire_time' => $this->nowTimestamp + 7200,
            ]],
            ['key3_l2_cache', false, [
                'origin_data' => 'l2CacheValue3',
                'expire_time' => $this->nowTimestamp + 7200,
            ]],
        ]);
        $cacheStub->method('add')->willReturnMap([
            ['key1_generate_lock', 1, 5, false],
            ['key2_generate_lock', 1, 5, false],
            ['key3_generate_lock', 1, 5, false],
        ]);
        $l2AvailableCache = new L2AvailableCache($cacheStub, $loggerStub, $datetime);
        $actual = $l2AvailableCache->get($key, function () use ($value) {
            return $value;
        });
        $this->assertEquals($excepted, $actual);
    }

    public function dpGetSceneNoL1CacheAndNoL2CacheAndHasGenerateLockAssertFalse()
    {
        return [
            ['key1', 'functionValue1', false],
            ['key2', 'functionValue2', false],
            ['key3', 'functionValue3', false],
        ];
    }

    /**
     * 环境：
     * 一级缓存不存在
     * 二级缓存不存在
     * 缓存生成锁已存在
     * 警告生成锁已存在
     * @dataProvider dpGetSceneNoL1CacheAndNoL2CacheAndHasGenerateLockAssertFalse
     * @param $key
     * @param $value
     * @param $excepted
     * @throws Exception
     */
    public function testGetSceneNoL1CacheAndNoL2CacheAndHasGenerateLockAssertFalse($key, $value, $excepted)
    {
        $datetime = new DateTime();
        $datetime->setTimestamp($this->nowTimestamp);
        /** @var Logger|MockObject $loggerStub */
        $loggerStub = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['error', 'info'])
            ->getMock();
        /** @var Repository|MockObject $cacheStub */
        $cacheStub = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'get', 'put', 'forget'])
            ->getMock();
        $cacheStub->method('get')->willReturnMap([
            ['key1_l1_cache', false, false],
            ['key2_l1_cache', false, false],
            ['key3_l1_cache', false, false],
            ['key1_l2_cache', false, false],
            ['key2_l2_cache', false, false],
            ['key3_l2_cache', false, false],
        ]);
        $l2AvailableCache = new L2AvailableCache($cacheStub, $loggerStub, $datetime);
        $actual = $l2AvailableCache->get($key, function () use ($value) {
            return $value;
        });
        $this->assertEquals($excepted, $actual);
    }

    public function dpGetSceneNoL1CacheAndHasL2CacheAndHasGenerateLockAndL2CacheIntervalLessThanSettingAssertSendAlarm()
    {
        return [
            ['key1', 'functionValue1'],
            ['key2', 'functionValue2'],
            ['key3', 'functionValue3'],
        ];
    }

    /**
     * 环境：
     * 一级缓存不存在
     * 二级缓存存在
     * 缓存生成锁已存在
     * 警告生成锁不存在
     * 二级缓存过期时间大于警戒时间
     * @dataProvider dpGetSceneNoL1CacheAndHasL2CacheAndHasGenerateLockAndL2CacheIntervalLessThanSettingAssertSendAlarm
     * @param $key
     * @param $value
     * @throws Exception
     */
    public function testGetSceneNoL1CacheAndHasL2CacheAndHasGenerateLockAndL2CacheIntervalLessThanSettingAssertSendAlarm($key, $value)
    {
        $datetime = new DateTime();
        $datetime->setTimestamp($this->nowTimestamp);
        /** @var Logger|MockObject $loggerMock */
        $loggerMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['error', 'info'])
            ->getMock();

        $loggerMock->expects($this->once())->method('info')->with($this->equalTo('二级缓存有效时间小于阈值，请尽快处理'));
        $params = [
            'alarm_callable' => function ($message) use ($loggerMock) {
                $loggerMock->info($message);
            },
        ];
        /** @var Repository|MockObject $cacheStub */
        $cacheStub = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'get', 'put', 'forget'])
            ->getMock();
        $cacheStub->method('get')->willReturnMap([
            ['key1_l1_cache', false, false],
            ['key2_l1_cache', false, false],
            ['key3_l1_cache', false, false],
            ['key1_l2_cache', false, [
                'origin_data' => 'l2CacheValue1',
                'expire_time' => $this->nowTimestamp + 1800,
            ]],
            ['key2_l2_cache', false, [
                'origin_data' => 'l2CacheValue2',
                'expire_time' => $this->nowTimestamp + 1800,
            ]],
            ['key3_l2_cache', false, [
                'origin_data' => 'l2CacheValue3',
                'expire_time' => $this->nowTimestamp + 1800,
            ]],
        ]);
        $cacheStub->method('add')->willReturnMap([
            ['key1_generate_lock', 1, 5, false],
            ['key2_generate_lock', 1, 5, false],
            ['key3_generate_lock', 1, 5, false],
            ['key1_alarm_lock', 1, 10, true],
            ['key2_alarm_lock', 1, 10, true],
            ['key3_alarm_lock', 1, 10, true],
        ]);
        $l2AvailableCache = new L2AvailableCache($cacheStub, $loggerMock, $datetime);
        $l2AvailableCache->get($key, function () use ($value) {
            return $value;
        }, $params);
    }

    public function dpGetSceneNoL1CacheAndNoL2CacheAndHasGenerateLockAssertSendAlarm()
    {
        return [
            ['key1', 'functionValue1'],
            ['key2', 'functionValue2'],
            ['key3', 'functionValue3'],
        ];
    }

    /**
     * 环境：
     * 一级缓存不存在
     * 二级缓存不存在
     * 缓存生成锁已存在
     * 缓存生成锁不存在
     * @dataProvider dpGetSceneNoL1CacheAndNoL2CacheAndHasGenerateLockAssertSendAlarm
     * @param $key
     * @param $value
     * @throws Exception
     */
    public function testGetSceneNoL1CacheAndNoL2CacheAndHasGenerateLockAssertSendAlarm($key, $value)
    {
        $datetime = new DateTime();
        $datetime->setTimestamp($this->nowTimestamp);
        /** @var Logger|MockObject $loggerMock */
        $loggerMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['error', 'info'])
            ->getMock();

        $loggerMock->expects($this->once())->method('info')->with($this->equalTo('二级缓存有效时间小于阈值，请尽快处理'));
        $params = [
            'alarm_callable' => function ($message) use ($loggerMock) {
                $loggerMock->info($message);
            },
        ];
        /** @var Repository|MockObject $cacheStub */
        $cacheStub = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'get', 'put', 'forget'])
            ->getMock();
        $cacheStub->method('get')->willReturnMap([
            ['key1_l1_cache', false, false],
            ['key2_l1_cache', false, false],
            ['key3_l1_cache', false, false],
            ['key1_l2_cache', false, false],
            ['key2_l2_cache', false, false],
            ['key3_l2_cache', false, false],
        ]);
        $cacheStub->method('add')->willReturnMap([
            ['key1_generate_lock', 1, 5, false],
            ['key2_generate_lock', 1, 5, false],
            ['key3_generate_lock', 1, 5, false],
            ['key1_alarm_lock', 1, 10, true],
            ['key2_alarm_lock', 1, 10, true],
            ['key3_alarm_lock', 1, 10, true],
        ]);
        $l2AvailableCache = new L2AvailableCache($cacheStub, $loggerMock, $datetime);
        $l2AvailableCache->get($key, function () use ($value) {
            return $value;
        }, $params);
    }

    public function dpGetSceneNoL1CacheAndNoGenerateLockAssertFunctionValue()
    {
        return [
            ['key1', 'functionValue1', 'functionValue1'],
            ['key2', 'functionValue2', 'functionValue2'],
            ['key3', 'functionValue3', 'functionValue3'],
        ];
    }

    /**
     * 环境：
     * 一级缓存不存在
     * 二级缓存存在
     * 缓存生成锁不存在
     * @dataProvider dpGetSceneNoL1CacheAndNoGenerateLockAssertFunctionValue
     * @param $key
     * @param $value
     * @param $excepted
     * @throws Exception
     */
    public function testGetSceneNoL1CacheAndNoGenerateLockAssertFunctionValue($key, $value, $excepted)
    {
        $datetime = new DateTime();
        $datetime->setTimestamp($this->nowTimestamp);
        /** @var Logger|MockObject $loggerStub */
        $loggerStub = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['error', 'info'])
            ->getMock();
        /** @var Repository|MockObject $cacheStub */
        $cacheStub = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'get', 'put', 'forget'])
            ->getMock();
        $cacheStub->method('get')->willReturnMap([
            ['key1_l1_cache', false, false],
            ['key2_l1_cache', false, false],
            ['key3_l1_cache', false, false],
            ['key1_l2_cache', false, false],
            ['key2_l2_cache', false, false],
            ['key3_l2_cache', false, false],
        ]);
        $cacheStub->method('add')->willReturnMap([
            ['key1_generate_lock', 1, 5, true],
            ['key2_generate_lock', 1, 5, true],
            ['key3_generate_lock', 1, 5, true],
        ]);
        $l2AvailableCache = new L2AvailableCache($cacheStub, $loggerStub, $datetime);
        $actual = $l2AvailableCache->get($key, function () use ($value) {
            return $value;
        });
        $this->assertEquals($excepted, $actual);
    }

    public function dpGetSceneNoL1CacheAndNoGenerateLockAssertCachePut()
    {
        return [
            ['key1', 'functionValue1', []],
            ['key2', 'functionValue2', [
                'l1_expire_interval' => 900,
                'l2_expire_interval' => 87000
            ]],
            ['key3', 'functionValue3', [
                'l1_expire_interval' => 1800,
                'l2_expire_interval' => 87000
            ]],
        ];
    }

    /**
     * 环境：
     * 一级缓存不存在
     * 二级缓存存在
     * 缓存生成锁不存在
     * @dataProvider dpGetSceneNoL1CacheAndNoGenerateLockAssertCachePut
     * @param $key
     * @param $value
     * @param $params
     * @throws Exception
     */
    public function testGetSceneNoL1CacheAndNoGenerateLockAssertCachePut($key, $value, $params)
    {
        $datetime = new DateTime();
        $datetime->setTimestamp($this->nowTimestamp);
        /** @var Logger|MockObject $loggerStub */
        $loggerStub = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['error', 'info'])
            ->getMock();
        /** @var Repository|MockObject $cacheMock */
        $cacheMock = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'get', 'put', 'forget'])
            ->getMock();
        $cacheMock->method('get')->willReturnMap([
            ['key1_l1_cache', false, false],
            ['key2_l1_cache', false, false],
            ['key3_l1_cache', false, false],
            ['key1_l2_cache', false, false],
            ['key2_l2_cache', false, false],
            ['key3_l2_cache', false, false],
        ]);
        $cacheMock->method('add')->willReturnMap([
            ['key1_generate_lock', 1, 5, true],
            ['key2_generate_lock', 1, 5, true],
            ['key3_generate_lock', 1, 5, true],
        ]);
        $l1ExpireInterval = $params['l1_expire_interval'] ?? 600;
        $l2ExpireInterval = $params['l2_expire_interval'] ?? 86400;
        $cacheMock->expects($this->exactly(2))->method('put')->withConsecutive([
            $this->equalTo($key . '_l1_cache'),
            $this->equalTo([
                'origin_data' => $value,
                'expire_time' => $this->nowTimestamp + $l1ExpireInterval,
            ]),
            $this->equalTo($l1ExpireInterval / 60)],
            [
                $this->equalTo($key . '_l2_cache'),
                $this->equalTo([
                    'origin_data' => $value,
                    'expire_time' => $this->nowTimestamp + $l2ExpireInterval,
                ]), $this->equalTo($l2ExpireInterval / 60)
            ]);
        $l2AvailableCache = new L2AvailableCache($cacheMock, $loggerStub, $datetime);
        $l2AvailableCache->get($key, function () use ($value) {
            return $value;
        }, $params);
    }

    /**
     * 环境：
     * 一级缓存不存在
     * 二级缓存存在
     * 缓存生成锁不存在
     * @dataProvider dpGetSceneNoL1CacheAndNoGenerateLockAssertCachePut
     * @param $key
     * @param $value
     * @param $params
     * @throws Exception
     */
    public function testGetSceneNoL1CacheAndNoGenerateLockAssertCacheForget($key, $value, $params)
    {
        $datetime = new DateTime();
        $datetime->setTimestamp($this->nowTimestamp);
        /** @var Logger|MockObject $loggerStub */
        $loggerStub = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['error', 'info'])
            ->getMock();
        /** @var Repository|MockObject $cacheMock */
        $cacheMock = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'get', 'put', 'forget'])
            ->getMock();
        $cacheMock->method('get')->willReturnMap([
            ['key1_l1_cache', false, false],
            ['key2_l1_cache', false, false],
            ['key3_l1_cache', false, false],
            ['key1_l2_cache', false, false],
            ['key2_l2_cache', false, false],
            ['key3_l2_cache', false, false],
        ]);
        $cacheMock->method('add')->willReturnMap([
            ['key1_generate_lock', 1, 5, true],
            ['key2_generate_lock', 1, 5, true],
            ['key3_generate_lock', 1, 5, true],
        ]);
        $cacheMock->expects($this->exactly(1))->method('forget')
            ->with($this->equalTo($key .'_generate_lock'));
        $l2AvailableCache = new L2AvailableCache($cacheMock, $loggerStub, $datetime);
        $l2AvailableCache->get($key, function () use ($value) {
            return $value;
        }, $params);
    }

    public function dpGetSceneNoL1CacheAndNoGenerateLockAndFunctionThrowExceptionAssertCacheValue()
    {
        return [
            ['key1', 'functionValue1', false],
            ['key2', 'functionValue2', 'l2CacheValue2'],
            ['key3', 'functionValue3', 'l2CacheValue3'],
        ];
    }

    /**
     * 环境：
     * 一级缓存不存在
     * 缓存生成锁不存在
     * @dataProvider dpGetSceneNoL1CacheAndNoGenerateLockAndFunctionThrowExceptionAssertCacheValue
     * @param $key
     * @param $value
     * @param $excepted
     * @throws Exception
     */
    public function testGetSceneNoL1CacheAndNoGenerateLockAndFunctionThrowExceptionAssertCacheValue($key, $value, $excepted)
    {
        $datetime = new DateTime();
        $datetime->setTimestamp($this->nowTimestamp);
        /** @var Logger|MockObject $loggerStub */
        $loggerStub = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['log'])
            ->getMock();
        /** @var Repository|MockObject $cacheStub */
        $cacheStub = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'get', 'put', 'forget'])
            ->getMock();
        $cacheStub->method('get')->willReturnMap([
            ['key1_l1_cache', false, false],
            ['key2_l1_cache', false, false],
            ['key3_l1_cache', false, false],
            ['key1_l2_cache', false, false],
            ['key2_l2_cache', false, [
                'origin_data' => 'l2CacheValue2',
                'expire_time' => $this->nowTimestamp + 1800,
            ]],
            ['key3_l2_cache', false, [
                'origin_data' => 'l2CacheValue3',
                'expire_time' => $this->nowTimestamp + 1800,
            ]],
        ]);
        $cacheStub->method('add')->willReturnMap([
            ['key1_generate_lock', 1, 5, true],
            ['key2_generate_lock', 1, 5, true],
            ['key3_generate_lock', 1, 5, true],
        ]);
        $l2AvailableCache = new L2AvailableCache($cacheStub, $loggerStub, $datetime);
        $actual = $l2AvailableCache->get($key, function () use ($value) {
            throw new Exception('获取数据失败', 2000);
        });
        $this->assertEquals($excepted, $actual);
    }

    public function dpGetSceneNoL1CacheAndNoGenerateLockAndFunctionThrowExceptionAssertLoggerError()
    {
        return [
            ['key1', 'functionValue1'],
            ['key2', 'functionValue2'],
            ['key3', 'functionValue3'],
        ];
    }

    /**
     * @dataProvider dpGetSceneNoL1CacheAndNoGenerateLockAndFunctionThrowExceptionAssertLoggerError
     * @param $key
     * @param $value
     * @throws Exception
     */
    public function testGetSceneNoL1CacheAndNoGenerateLockAndFunctionThrowExceptionAssertLoggerError($key, $value)
    {
        $datetime = new DateTime();
        $datetime->setTimestamp($this->nowTimestamp);
        /** @var Logger|MockObject $loggerMock */
        $loggerMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['log'])
            ->getMock();
        $loggerMock->expects($this->once())->method('log')
            ->with($this->equalTo('error'), $this->equalTo('获取数据失败'), $this->equalTo([]));
        /** @var Repository|MockObject $cacheStub */
        $cacheStub = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'get', 'put', 'forget'])
            ->getMock();
        $cacheStub->method('get')->willReturnMap([
            ['key1_l1_cache', false, false],
            ['key2_l1_cache', false, false],
            ['key3_l1_cache', false, false],
            ['key1_l2_cache', false, false],
            ['key2_l2_cache', false, [
                'origin_data' => 'l2CacheValue2',
                'expire_time' => $this->nowTimestamp + 1800,
            ]],
            ['key3_l2_cache', false, [
                'origin_data' => 'l2CacheValue3',
                'expire_time' => $this->nowTimestamp + 1800,
            ]],
        ]);
        $cacheStub->method('add')->willReturnMap([
            ['key1_generate_lock', 1, 5, true],
            ['key2_generate_lock', 1, 5, true],
            ['key3_generate_lock', 1, 5, true],
        ]);
        $l2AvailableCache = new L2AvailableCache($cacheStub, $loggerMock, $datetime);
        $l2AvailableCache->get($key, function () use ($value) {
            throw new Exception('获取数据失败', 2000);
        });
    }

    /**
     * @dataProvider dpGetSceneNoL1CacheAndNoGenerateLockAndFunctionThrowExceptionAssertLoggerError
     * @param $key
     * @param $value
     * @throws Exception
     */
    public function testGetSceneNoL1CacheAndNoGenerateLockAndFunctionThrowExceptionAssertSendAlarm($key, $value)
    {
        $datetime = new DateTime();
        $datetime->setTimestamp($this->nowTimestamp);
        /** @var Logger|MockObject $loggerMock */
        $loggerMock = $this->getMockBuilder(Logger::class)
            ->disableOriginalConstructor()
            ->setMethods(['info', 'log'])
            ->getMock();
        $loggerMock->expects($this->once())->method('info')
            ->with($this->equalTo('获取数据失败'), $this->equalTo([]));
        $params = [
            'alarm_callable' => function ($message) use ($loggerMock) {
                $loggerMock->info($message);
            }
        ];
        /** @var Repository|MockObject $cacheStub */
        $cacheStub = $this->getMockBuilder(Repository::class)
            ->disableOriginalConstructor()
            ->setMethods(['add', 'get', 'put', 'forget'])
            ->getMock();
        $cacheStub->method('get')->willReturnMap([
            ['key1_l1_cache', false, false],
            ['key2_l1_cache', false, false],
            ['key3_l1_cache', false, false],
            ['key1_l2_cache', false, false],
            ['key2_l2_cache', false, [
                'origin_data' => 'l2CacheValue2',
                'expire_time' => $this->nowTimestamp + 1800,
            ]],
            ['key3_l2_cache', false, [
                'origin_data' => 'l2CacheValue3',
                'expire_time' => $this->nowTimestamp + 1800,
            ]],
        ]);
        $cacheStub->method('add')->willReturnMap([
            ['key1_generate_lock', 1, 5, true],
            ['key2_generate_lock', 1, 5, true],
            ['key3_generate_lock', 1, 5, true],
            ['key1_alarm_lock', 1, 10, true],
            ['key2_alarm_lock', 1, 10, true],
            ['key3_alarm_lock', 1, 10, true],
        ]);
        $l2AvailableCache = new L2AvailableCache($cacheStub, $loggerMock, $datetime);
        $l2AvailableCache->get($key, function () use ($value) {
            throw new Exception('获取数据失败', 2000);
        }, $params);
    }
}