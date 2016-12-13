<?php

namespace yiisolutions\fixer;

use Yii;
use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\caching\Cache;
use yii\helpers\Json;
use yii\httpclient\Client as HttpClient;
use yii\web\HttpException;

/**
 * Fixer.io API-client class.
 *
 * Usage:
 *
 * ```php
 * $fixerClient = Yii::createObject(yiisolutions\fixer\Client::className());
 *
 * $base = 'RUB';
 * $symbols = ['USD', 'EUR'];           // optional argument
 * $latest = $fixerClient->latest($base, $symbols);
 *
 * // print latest USD rate
 * echo $latest['USD'];
 *
 * // print latest EUR rate
 * echo $latest['EUR'];
 *
 * ```
 * @package yiisolutions\fixer
 */
class Client extends Component
{
    /**
     * @var string API end point
     */
    public $endPoint = 'https://api.fixer.io/';

    /**
     * @var string When latest() method first param missing use this default base value
     */
    public $defaultBase;

    /**
     * @var array|string default param for latest() method
     */
    public $defaultSymbols;

    /**
     * @var bool disable/enable caching for API-responses
     */
    public $useCache = true;

    /**
     * @var string cache component id
     */
    public $cacheComponentId = 'cache';

    /**
     * @var integer cache lifetime
     */
    public $cacheDuration = 0;

    /**
     * @var string
     */
    public $cacheKeyPrefix = 'yiisolutions-fixer-client-';

    /**
     * @var HttpClient
     */
    private $_httpClient;

    /**
     * @var Cache
     */
    private $_cache;

    /**
     * @inheritdoc
     */
    public function init()
    {
        if (!empty($this->defaultSymbols)) {
            if (!is_array($this->defaultSymbols)) {
                $this->defaultSymbols = array_filter(explode(',', $this->defaultSymbols));
            }
        }
    }

    /**
     * Get the latest foreign exchange reference rates from fixer.io API.
     * @param string $base
     * @param array $symbols
     * @return array contains 'base', 'date' and 'rates' array
     * @throws InvalidConfigException
     */
    public function latest($base = null, array $symbols = [])
    {
        $queryParams = $this->buildQueryParams($base, $symbols);

        return $this->throughCache('latest', $queryParams);
    }

    /**
     * Get historical rates for any day since 1999.
     * @param \DateTime|string|integer $date
     * @param string|null $base
     * @param array $symbols
     * @return mixed
     */
    public function historical($date, $base = null, $symbols = [])
    {
        $queryParams = $this->buildQueryParams($base, $symbols);

        if ($date instanceof \DateTime) {
            $path = $date->format('Y-m-d');
        } elseif (is_int($date)) {
            $path = date('Y-m-d', time());
        } elseif (preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $date)) {
            $path = $date;
        } else {
            $path = date('Y-m-d', strtotime($date));
        }

        return $this->throughCache($path, $queryParams);
    }

    /**
     * @param $path
     * @param $queryParams
     * @return mixed
     */
    protected function throughCache($path, $queryParams)
    {
        if (!$this->useCache) {
            return $this->performRequest($path, $queryParams);
        }

        $cache = $this->getCache();
        $key = $this->cacheKeyPrefix . md5($path . json_encode($queryParams));
        $data = $cache->get($key);

        if ($data === false) {
            $data = $this->performRequest($path, $queryParams);
            $cache->set($key, $data, $this->cacheDuration);
        }

        return $data;
    }

    /**
     * @param $path
     * @param $queryParams
     * @return mixed
     * @throws HttpException
     */
    protected function performRequest($path, $queryParams)
    {
        $httpClient = $this->getHttpClient();
        $request = $httpClient->createRequest()
            ->setUrl($this->endPoint . $path . '?' . http_build_query($queryParams))
            ->addHeaders([
                'Accept' => 'application/json',
            ]);

        $response = $request->send();

        if (!$response->isOk) {
            throw new HttpException($response->statusCode, $response->content);
        }

        return Json::decode($response->content);
    }

    /**
     * @param $base
     * @param $symbols
     * @return array
     * @throws InvalidConfigException
     */
    protected function buildQueryParams($base, $symbols)
    {
        if (empty($base)) {
            if (empty($this->defaultBase)) {
                throw new InvalidConfigException("Missing 'defaultBase' component property");
            }

            $base = $this->defaultBase;
        }

        if (empty($symbols)) {
            $symbols = $this->defaultSymbols;
        }

        return [
            'base' => $base,
            'symbols' => implode(',', $symbols),
        ];
    }

    /**
     * Get cache component
     * @return Cache
     * @throws InvalidConfigException
     */
    private function getCache()
    {
        if (!($this->_cache instanceof Cache)) {
            if (!Yii::$app->has($this->cacheComponentId)) {
                throw new InvalidConfigException("Cache component ID '{$this->cacheComponentId}' not found");
            }
            $this->_cache = Yii::$app->get($this->cacheComponentId);
        }

        return $this->_cache;
    }

    /**
     * Get HTTP client.
     * @return HttpClient
     */
    protected function getHttpClient()
    {
        if (!($this->_httpClient instanceof HttpClient)) {
            $this->_httpClient = new HttpClient();
        }

        return $this->_httpClient;
    }
}