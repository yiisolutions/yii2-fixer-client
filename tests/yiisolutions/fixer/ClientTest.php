<?php

namespace yiisolutions\fixer;

use yii\web\Application;

class ClientTest extends \PHPUnit_Framework_TestCase
{
    const ITERATION_COUNT = 100;

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
     * Data provider for latest method
     *
     * @return \Generator
     */
    public function latestMethodDataProvider()
    {
        $currencyList = [
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
}
