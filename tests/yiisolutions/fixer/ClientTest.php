<?php

namespace yiisolutions\fixer;

use yii\web\Application;

class ClientTest extends \PHPUnit_Framework_TestCase
{
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
}
