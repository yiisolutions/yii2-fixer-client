<?php

namespace yiisolutions\fixer;

use yii\caching\FileCache;
use yii\web\Application;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    const ITERATION_COUNT = 5;

    /**
     * @var Application
     */
    private $_app;

    public function setUp()
    {
        parent::setUp();
        $this->_app = new Application([
            'id' => 'test',
            'basePath' => __DIR__ . '/../../../',
            'components' => [
                'currency' => [
                    'class' => 'yiisolutions\fixer\Client',
                    'defaultSymbols' => 'USD,EUR',
                ],
            ],
        ]);
    }

    public function testComponentAccessible()
    {
        $this->assertTrue($this->_app->has('currency'));
    }

    public function testComponentInstance()
    {
        $this->assertInstanceOf(Client::class, $this->_app->get('currency'));
    }

    /**
     * @param $base
     * @param $symbols
     *
     * @dataProvider latestMethodDataProvider
     */
    public function testLatestMethodCallCorrect($base, $symbols)
    {
        $payload = compact('base', 'symbols');

        $client = $this->getMockClient(['buildQueryParams', 'throughCache']);

        $client->expects($this->once())
            ->method('buildQueryParams')
            ->with($this->equalTo($base), $this->equalTo($symbols))
            ->will($this->returnValue($payload));

        $client->expects($this->once())
            ->method('throughCache')
            ->with($this->equalTo('latest'), $this->equalTo($payload))
            ->will($this->returnValue($payload));

        $result = $client->latest($base, $symbols);

        $this->assertEquals($payload, $result);
    }

    /**
     * @param $date
     * @param $base
     * @param $symbols
     *
     * @dataProvider historicalMethodDataProvider
     */
    public function testHistoricalMethodCallCorrect($date, $base, $symbols)
    {
        $payload = compact($base, $symbols);

        $client = $this->getMockClient(['buildQueryParams', 'buildHistoricalPath', 'throughCache']);

        $client->expects($this->once())
            ->method('buildQueryParams')
            ->with($this->equalTo($base), $this->equalTo($symbols))
            ->will($this->returnValue($payload));

        $client->expects($this->once())
            ->method('buildHistoricalPath')
            ->with($this->equalTo($date))
            ->will($this->returnValue($date));

        $client->expects($this->once())
            ->method('throughCache')
            ->with($this->equalTo($date), $this->equalTo($payload))
            ->will($this->returnValue($payload));

        $result = $client->historical($date, $base, $symbols);

        $this->assertEquals($payload, $result);
    }

    /**
     * @param $base
     * @param $symbols
     *
     * @dataProvider latestMethodDataProvider
     */
    public function testThroughCacheMethodNotUseCache($base, $symbols)
    {
        $queryParams = compact('base', 'symbols');

        $client = $this->getMockClient(['buildQueryParams', 'performRequest', 'getCache']);
        $client->useCache = false;

        $client->expects($this->once())
            ->method('buildQueryParams')
            ->with($this->equalTo($base), $this->equalTo($symbols))
            ->will($this->returnValue($queryParams));

        $client->expects($this->once())
            ->method('performRequest')
            ->with($this->equalTo('latest'), $this->equalTo($queryParams))
            ->will($this->returnValue(true));

        $this->assertTrue($client->latest($base, $symbols));
    }

    /**
     * @param $base
     * @param $symbols
     *
     * @dataProvider latestMethodDataProvider
     */
    public function testThroughCacheMethodUseCache($base, $symbols)
    {
        $queryParams = compact('base', 'symbols');
        $cacheKey = md5($base);

        $cache = $this->getMockCache(['get', 'set']);

        $client = $this->getMockClient(['buildQueryParams', 'buildCacheKey', 'performRequest', 'getCache']);
        $client->useCache = true;
        $client->cacheDuration = 0;

        $client->expects($this->once())
            ->method('buildQueryParams')
            ->with($this->equalTo($base), $this->equalTo($symbols))
            ->will($this->returnValue($queryParams));

        $client->expects($this->once())
            ->method('getCache')
            ->will($this->returnValue($cache));

        $client->expects($this->once())
            ->method('buildCacheKey')
            ->with($this->equalTo('latest'), $queryParams)
            ->will($this->returnValue($cacheKey));

        $cache->expects($this->once())
            ->method('get')
            ->with($this->equalTo($cacheKey))
            ->will($this->returnValue(false));

        $client->expects($this->once())
            ->method('performRequest')
            ->with($this->equalTo('latest'), $this->equalTo($queryParams))
            ->will($this->returnValue(true));

        $cache->expects($this->once())
            ->method('set')
            ->with($this->equalTo($cacheKey), $this->equalTo(true), $this->equalTo($client->cacheDuration));

        $this->assertTrue($client->latest($base, $symbols));
    }

    /**
     * @param array $methods
     * @return \PHPUnit_Framework_MockObject_MockObject|Client
     */
    private function getMockClient(array $methods)
    {
        return $this->getMockBuilder(Client::class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    /**
     * @param array $methods
     * @return \PHPUnit_Framework_MockObject_MockObject
     */
    private function getMockCache(array $methods)
    {
        return $this->getMockBuilder(FileCache::class)
            ->disableOriginalConstructor()
            ->setMethods($methods)
            ->getMock();
    }

    /**
     * Data provider for latest method
     *
     * @return \Generator
     */
    public function latestMethodDataProvider()
    {
        $currencyList = $this->getAvailableCurrency();

        for ($i = 0; $i < self::ITERATION_COUNT; $i++) {
            $baseKey = array_rand($currencyList, 1);
            $symbolsKeys = array_rand($currencyList, rand(2, count($currencyList)));
            yield [
                rand(0, 1) ? $currencyList[array_rand($currencyList, 1)] : null,
                rand(0, 1) ? array_filter($currencyList, function($key) use ($baseKey, $symbolsKeys) {
                    return in_array($key, $symbolsKeys) && ($key != $baseKey);
                }, ARRAY_FILTER_USE_KEY) : [],
            ];
        }
    }

    /**
     * Data provider for historical method
     *
     * @return \Generator
     */
    public function historicalMethodDataProvider()
    {
        $currencyList = $this->getAvailableCurrency();

        for ($i = 0; $i < self::ITERATION_COUNT; $i++) {
            $date = date('Y-m-d', rand(strtotime('-30 days'), time()));
            $baseKey = array_rand($currencyList, 1);
            $symbolsKeys = array_rand($currencyList, rand(2, count($currencyList)));
            yield [
                rand(0, 1) ? new \DateTime($date) : (rand(0, 1) ? strtotime($date) : $date),
                rand(0, 1) ? $currencyList[array_rand($currencyList, 1)] : null,
                rand(0, 1) ? array_filter($currencyList, function($key) use ($baseKey, $symbolsKeys) {
                    return in_array($key, $symbolsKeys) && ($key != $baseKey);
                }, ARRAY_FILTER_USE_KEY) : [],
            ];
        }
    }

    private function getAvailableCurrency()
    {
        return [
            'AUD',
            'BGN',
            'BRL',
            'CAD',
            'CHF',
            'CNY',
            'CZK',
            'DKK',
            'GBP',
            'HKD',
            'HRK',
            'HUF',
            'IDR',
            'ILS',
            'INR',
            'JPY',
            'KRW',
            'MXN',
            'MYR',
            'NOK',
            'NZD',
            'PHP',
            'PLN',
            'RON',
            'RUB',
            'SEK',
            'SGD',
            'THB',
            'TRY',
            'USD',
            'ZAR',
        ];
    }
}
