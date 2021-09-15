<?php
/**
 * Created by PhpStorm.
 * User: Luwei
 * Date: 2019/11/3
 * Time: 18:12
 */

namespace AvailableCache\Tests\Engines;

use AvailableCache\Engines\SimpleCache;
use AvailableCache\Tests\TestCase;
use Illuminate\Cache\Repository;
use PHPUnit\Framework\MockObject\MockObject;

class SimpleCacheTest extends TestCase
{
    public function dpGetSceneNoCacheAssertFunctionValue()
    {
        return [
            ['key1', 'functionValue1', 'functionValue1'],
            ['key2', 'functionValue2', 'functionValue2'],
            ['key3', 'functionValue3', 'functionValue3'],
        ];
    }

    /**
     * @param $key
     * @param $value
     * @param $excepted
     * @dataProvider dpGetSceneNoCacheAssertFunctionValue
     */
    public function testGetSceneNoCacheAssertFunctionValue($key, $value, $excepted)
    {
        /** @var Repository|MockObject $cacheStub */
        $cacheStub = $this->getMockBuilder(Repository::class)->disableOriginalConstructor()->setMethods(['get', 'put'])->getMock();
        $cacheStub->method('get')->willReturn(false);

        $simpleCache = $this->getSimpleCacheInstance($cacheStub);
        $actual = $simpleCache->get($key, function () use ($value) {
            return $value;
        });

        $this->assertEquals($excepted, $actual);
    }

    public function dpGetSceneHasCacheAssertCacheValue()
    {
        return [
            ['key1', 'functionValue1', 'cacheValue1'],
            ['key2', 'functionValue2', 'cacheValue2'],
            ['key3', 'functionValue3', 'cacheValue3'],
        ];
    }

    /**
     * @param $key
     * @param $value
     * @param $excepted
     * @dataProvider dpGetSceneHasCacheAssertCacheValue
     */
    public function testGetSceneHasCacheAssertCacheValue($key, $value, $excepted)
    {
        /** @var Repository|MockObject $cacheStub */
        $cacheStub = $this->getMockBuilder(Repository::class)->disableOriginalConstructor()->setMethods(['get', 'put'])->getMock();
        $cacheStub->method('get')->willReturnMap([
            ['key1', false, 'cacheValue1'],
            ['key2', false, 'cacheValue2'],
            ['key3', false, 'cacheValue3'],
        ]);

        $simpleCache = $this->getSimpleCacheInstance($cacheStub);
        $actual = $simpleCache->get($key, function () use ($value) {
            return $value;
        });

        $this->assertEquals($excepted, $actual);
    }

    public function dpGetSceneHasCacheButOverwriteAssertFunctionValue()
    {
        return [
            ['key1', 'functionValue1', [], true, 'functionValue1'],
            ['key2', 'functionValue2', [], true, 'functionValue2'],
            ['key3', 'functionValue3', [], true, 'functionValue3'],
        ];
    }

    /**
     * @param $key
     * @param $value
     * @param $params
     * @param $overwrite
     * @param $excepted
     * @dataProvider dpGetSceneHasCacheButOverwriteAssertFunctionValue
     */
    public function testGetSceneHasCacheButOverwriteAssertFunctionValue($key, $value, $params, $overwrite, $excepted)
    {
        /** @var Repository|MockObject $cache */
        $cacheStub = $this->getMockBuilder(Repository::class)->disableOriginalConstructor()->setMethods(['get', 'put'])->getMock();
        $cacheStub->method('get')->willReturnMap([
            ['key1', false, 'cacheValue1'],
            ['key2', false, 'cacheValue2'],
            ['key3', false, 'cacheValue3'],
        ]);

        $simpleCache = $this->getSimpleCacheInstance($cacheStub);
        $actual = $simpleCache->get($key, function () use ($value) {
            return $value;
        }, $params, $overwrite);

        $this->assertEquals($excepted, $actual);
    }

    public function dpGetSceneNoCacheExceptMethodPut()
    {
        return [
            ['key1', 'functionValue1', false, [], 10],
            ['key2', 'functionValue2', false, ['expire_interval' => 900], 15],
            ['key3', 'functionValue3', false, ['expire_interval' => 1800], 30],
        ];
    }

    /**
     * @param $key
     * @param $value
     * @param $overwrite
     * @param $params
     * @param $minutes
     * @dataProvider dpGetSceneNoCacheExceptMethodPut
     */
    public function testGetSceneNoCacheExceptMethodPut($key, $value, $overwrite, $params, $minutes)
    {
        /** @var Repository|MockObject $cacheMock */
        $cacheMock = $this->getMockBuilder(Repository::class)->disableOriginalConstructor()->setMethods(['get', 'put'])->getMock();
        $cacheMock->method('get')->willReturn(false);
        $cacheMock->expects($this->once())->method('put')
            ->with($this->equalTo($key), $this->equalTo($value), $this->equalTo($minutes));

        $simpleCache = $this->getSimpleCacheInstance($cacheMock);
        $simpleCache->get($key, function () use ($value) {
            return $value;
        }, $params, $overwrite);
    }

    public function dpGetSceneHasCacheButOverwriteExceptMethodPut()
    {
        return [
            ['key1', 'functionValue1', true, [], 10],
            ['key2', 'functionValue2', true, ['expire_interval' => 900], 15],
            ['key3', 'functionValue3', true, ['expire_interval' => 1800], 30],
        ];
    }

    /**
     * @param $key
     * @param $value
     * @param $overwrite
     * @param $params
     * @param $minutes
     * @dataProvider dpGetSceneHasCacheButOverwriteExceptMethodPut
     */
    public function testGetSceneHasCacheButOverwriteExceptMethodPut($key, $value, $overwrite, $params, $minutes)
    {
        /** @var Repository|MockObject $cacheMock */
        $cacheMock = $this->getMockBuilder(Repository::class)->disableOriginalConstructor()->setMethods(['get', 'put'])->getMock();
        $cacheMock->method('get')->willReturnMap([
            ['key1', false, 'cacheValue1'],
            ['key2', false, 'cacheValue2'],
            ['key3', false, 'cacheValue3'],
        ]);
        $cacheMock->expects($this->once())->method('put')
            ->with($this->equalTo($key), $this->equalTo($value), $this->equalTo($minutes));

        $simpleCache = $this->getSimpleCacheInstance($cacheMock);
        $simpleCache->get($key, function () use ($value) {
            return $value;
        }, $params, $overwrite);
    }

    /**
     * @param $cache
     * @return SimpleCache
     */
    protected function getSimpleCacheInstance($cache)
    {
        return new SimpleCache($cache);
    }
}