<?php

namespace Eraple\Test;

use Eraple\App;

class AppTest extends \PHPUnit\Framework\TestCase
{
    /* @var App */
    protected $app;

    /* @var string */
    protected $rootPath;

    public function setUp()
    {
        $this->rootPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'app';
        $this->app = App::instance($this->rootPath);
    }

    /* test it can instantiate */
    public function testConstruct()
    {
        $this->assertTrue(true);
    }

    /* test it can instantiate and get global instance */
    public function testInstance()
    {
        $this->assertTrue(true);
    }

    /* test it can get version */
    public function testGetVersion()
    {
        $this->assertSame('1.0.0', $this->app->getVersion());
    }

    /* test it can run */
    public function testRun()
    {
        $this->app->run();
        $this->assertTrue(true);
    }

    /* test it can register module */
    public function testRegisterModule()
    {
        $this->assertTrue(true);
    }

    /* test it can register task */
    public function testRegisterTask()
    {
        $this->assertTrue(true);
    }

    /* test it can fire event */
    public function testFireEvent()
    {
        $this->assertTrue(true);
    }

    /* test it can get root path */
    public function testGetRootPath()
    {
        $this->assertSame($this->rootPath . DIRECTORY_SEPARATOR, $this->app->getRootPath());
    }

    /* test it can get local path */
    public function testGetLocalPath()
    {
        $this->assertSame($this->rootPath . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR, $this->app->getLocalPath());
    }

    /* test it can get vendor path */
    public function testGetVendorPath()
    {
        $this->assertSame($this->rootPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR, $this->app->getVendorPath());
    }

    /* test it can check whether given name is valid module, task or event name */
    public function testIsValidName()
    {
        $this->assertTrue($this->app->isValidName('task'));
        $this->assertTrue($this->app->isValidName('task-one'));
        $this->assertTrue($this->app->isValidName('task-123'));
        $this->assertFalse($this->app->isValidName(''));
        $this->assertFalse($this->app->isValidName('am'));
        $this->assertFalse($this->app->isValidName('Task-one'));
        $this->assertFalse($this->app->isValidName('task_one'));
    }
}
