<?php

namespace Eraple\Test;

use Eraple\App;
use Zend\Di\Injector;
use Zend\Di\Definition\RuntimeDefinition;
use Eraple\Exception\CircularDependencyException;
use Eraple\Test\Data\Stub\SampleModule;
use Eraple\Test\Data\Stub\InvalidNameModule;
use Eraple\Test\Data\Stub\NotImplementedModule;
use Eraple\Test\Data\Stub\SampleTask;
use Eraple\Test\Data\Stub\InvalidNameTask;
use Eraple\Test\Data\Stub\NotImplementedTask;
use Eraple\Test\Data\Stub\FireEventTask;
use Eraple\Test\Data\Stub\FireHighPriorityEventTask;
use Eraple\Test\Data\Stub\FireLowPriorityEventTask;
use Eraple\Test\Data\Stub\FireBeforeEventTask;
use Eraple\Test\Data\Stub\FireAfterEventTask;
use Eraple\Test\Data\Stub\TaskAFollowsTaskC;
use Eraple\Test\Data\Stub\TaskBFollowsTaskA;
use Eraple\Test\Data\Stub\TaskCFollowsTaskB;

class AppTest extends \PHPUnit\Framework\TestCase
{
    /* @var App */
    protected $app;

    /* @var string */
    protected $rootPath;

    public function setUp()
    {
        $this->rootPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'app';
        $this->app = new App($this->rootPath);
    }

    /* test it can instantiate and set root path, injector and runtime definition */
    public function testConstruct()
    {
        $app = new App('some_path_here');
        $this->assertSame('some_path_here' . DIRECTORY_SEPARATOR, $app->getRootPath());
        $this->assertInstanceOf(Injector::class, $app->getInjector());
        $this->assertInstanceOf(RuntimeDefinition::class, $app->getDefinition());
    }

    /* test it can instantiate and get global instance */
    public function testInstance()
    {
        $appGlobal = App::instance();
        $appLocal = new App();
        $this->assertNotSame($appGlobal, $appLocal);
        $this->assertSame($appGlobal, App::instance());
    }

    /* test it can get version */
    public function testGetVersion()
    {
        $this->assertSame('1.0.0', $this->app->getVersion());
    }

    /* test it can run */
    public function testRun() { $this->assertTrue(true); }

    /* test it can register module */
    public function testRegisterModule()
    {
        $this->app->registerModule(SampleModule::class);
        $this->app->registerModule(InvalidNameModule::class);
        $this->app->registerModule(NotImplementedModule::class);
        $this->assertSame(['sample-module' => SampleModule::class], $this->app->getModules());
    }

    /* test it can register task */
    public function testRegisterTask()
    {
        $this->app->registerTask(SampleTask::class);
        $this->app->registerModule(InvalidNameTask::class);
        $this->app->registerModule(NotImplementedTask::class);
        $this->assertSame(['sample-task' => SampleTask::class], $this->app->getTasks());
    }

    /* test it can fire event */
    public function testFire()
    {
        /* test sequence of tasks based on priority and event */
        $this->app->registerTask(FireLowPriorityEventTask::class);
        $this->app->registerTask(FireHighPriorityEventTask::class);
        $this->app->registerTask(FireEventTask::class);
        $data = $this->app->fire('something-happened', ['key' => '(fired)']);
        $this->assertSame(['key' => '(fired) high on low'], $data);

        /* test sequence of tasks based on before and after event */
        $this->app->registerTask(FireBeforeEventTask::class);
        $this->app->registerTask(FireAfterEventTask::class);
        $data = $this->app->fire('something-happened', ['key' => '(fired)']);
        $this->assertSame(['key' => '(fired) before high on low after'], $data);
    }

    /* test it can run tasks by position */
    public function testRunTasksByPosition() { $this->assertTrue(true); }

    /* test it can get tasks by position */
    public function testGetTasksByPosition() { $this->assertTrue(true); }

    /* test it can run task */
    public function testRunTask()
    {
        $this->expectException(CircularDependencyException::class);
        $this->app->registerTask(TaskAFollowsTaskC::class);
        $this->app->registerTask(TaskBFollowsTaskA::class);
        $this->app->registerTask(TaskCFollowsTaskB::class);
        $this->app->runTask(TaskAFollowsTaskC::class);
    }

    /* test it can check entry exists */
    public function testHas()
    {
        $this->assertFalse($this->app->has('name'));
        $this->app->set('name', 'Amit Sidhpura');
        $this->assertTrue($this->app->has('name'));
    }

    /* test it can get entry */
    public function testGet()
    {
        $this->app->set('name', 'Amit Sidhpura');
        $this->assertSame('Amit Sidhpura', $this->app->get('name'));
    }

    /* test it can set entry */
    public function testSet()
    {
        $this->app->set('name', 'Amit Sidhpura');
        $this->assertSame(['name' => ['instance' => 'Amit Sidhpura']], $this->app->getResources());
    }

    /* test it can get runtime definition */
    public function testGetInjector()
    {
        /* test covered in testConstruct */
        $this->assertTrue(true);
    }

    /* test it can get modules */
    public function testGetDefinition()
    {
        /* test covered in testConstruct */
        $this->assertTrue(true);
    }

    /* test it can get injector */
    public function testGetModules()
    {
        /* test covered in testRegisterModule */
        $this->assertTrue(true);
    }

    /* test it can get tasks */
    public function testGetTasks()
    {
        /* test covered in testRegisterTask */
        $this->assertTrue(true);
    }

    /* test it can get resources */
    public function testGetResources()
    {
        /* test covered in testSet */
        $this->assertTrue(true);
    }

    /* test it can get instance stack */
    public function testGetInstanceStack() { $this->assertTrue(true); }

    /* test it can flush all modules, tasks, resources and instance stack of the application */
    public function testFlush() { $this->assertTrue(true); }

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

    /* test it can convert delimiters string to camelcase string */
    public function testCamelize()
    {
        $this->assertSame('taskOne', $this->app->camelize('task-one'));
        $this->assertSame('taskOne', $this->app->camelize('task_one', '_'));
        $this->assertSame('taskone', $this->app->camelize('taskone'));
    }

    /* test it can convert camelcase string to delimiters string */
    public function testUncamelize()
    {
        $this->assertSame('task-one', $this->app->uncamelize('taskOne'));
        $this->assertSame('task_one', $this->app->uncamelize('taskOne', '_'));
        $this->assertSame('taskone', $this->app->uncamelize('taskone'));
    }

    /* test it can get resource with name and arguments */
    public function testCall()
    {
        $this->app->set('my-name', 'Amit Sidhpura');
        /** @noinspection PhpUndefinedMethodInspection */
        $this->assertSame('Amit Sidhpura', $this->app->myName());
    }
}
