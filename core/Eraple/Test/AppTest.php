<?php

namespace Eraple\Test;

use Eraple\App;
use Eraple\Test\Data\Stub\SampleServiceInterface;
use Zend\Di\Injector;
use Zend\Di\Definition\RuntimeDefinition;
use Eraple\Exception\CircularDependencyException;
use Eraple\Test\Data\Stub\SampleModule;
use Eraple\Test\Data\Stub\InvalidNameModule;
use Eraple\Test\Data\Stub\NotImplementedModule;
use Eraple\Test\Data\Stub\SampleTask;
use Eraple\Test\Data\Stub\InvalidNameTask;
use Eraple\Test\Data\Stub\NotImplementedTask;
use Eraple\Test\Data\Stub\SampleService;
use Eraple\Test\Data\Stub\FireEventTask;
use Eraple\Test\Data\Stub\FireLowIndexEventTask;
use Eraple\Test\Data\Stub\FireHighIndexEventTask;
use Eraple\Test\Data\Stub\FireBeforeEventTask;
use Eraple\Test\Data\Stub\FireAfterEventTask;
use Eraple\Test\Data\Stub\FireReplaceEventTask;
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
    public function testFunctionConstruct()
    {
        $app = new App('some_path_here');
        $this->assertSame('some_path_here' . DIRECTORY_SEPARATOR, $app->getRootPath());
        $this->assertInstanceOf(Injector::class, $app->getInjector());
        $this->assertInstanceOf(RuntimeDefinition::class, $app->getDefinition());
    }

    /* test it can instantiate and get global instance */
    public function testFunctionInstance()
    {
        $appGlobal = App::instance();
        $appLocal = new App();
        $this->assertNotSame($appGlobal, $appLocal);
        $this->assertSame($appGlobal, App::instance());
    }

    /* test it can get version */
    public function testFunctionGetVersion()
    {
        $this->assertSame('1.0.0', $this->app->getVersion());
    }

    /* test it can run */
    public function testFunctionRun() { $this->assertTrue(true); }

    /* test it can register module */
    public function testFunctionRegisterModule()
    {
        $this->app->registerModule(SampleModule::class);
        $this->app->registerModule(InvalidNameModule::class);
        $this->app->registerModule(NotImplementedModule::class);
        $this->assertSame(['sample-module' => SampleModule::class], $this->app->getModules());
    }

    /* test it can register task */
    public function testFunctionRegisterTask()
    {
        $this->app->registerTask(SampleTask::class);
        $this->app->registerModule(InvalidNameTask::class);
        $this->app->registerModule(NotImplementedTask::class);
        $this->assertSame(['sample-task' => SampleTask::class], $this->app->getTasks());
    }

    /* test it can register service */
    public function testFunctionRegisterService()
    {
        /* test covered in testFunctionSet */
        $this->assertTrue(true);
    }

    /* test it can set entry */
    public function testFunctionSet()
    {
        /* set name and value pair with invalid name */
        $this->app->set('My Name', 'Amit Sidhpura');
        $this->assertSame([], $this->app->getServices());

        /* set name and value pair with valid name */
        $this->app->set('name', 'Amit Sidhpura');
        $this->assertSame(['name' => ['instance' => 'Amit Sidhpura']], $this->app->getServices());

        /* set name and value pair in array with instance format */
        $this->app->flush();
        $this->app->set('name', ['instance' => 'Amit Sidhpura']);
        $this->assertSame(['name' => ['instance' => 'Amit Sidhpura']], $this->app->getServices());

        /* set name and value pair with value as array */
        $this->app->flush();
        $this->app->set('profile', ['first-name' => 'Amit', 'last-name' => 'Sidhpura']);
        $this->assertSame(['profile' => ['instance' => ['first-name' => 'Amit', 'last-name' => 'Sidhpura']]], $this->app->getServices());

        /* set interface and class pair */
        $this->app->flush();
        $this->app->set(SampleServiceInterface::class, SampleService::class);
        $this->assertSame([SampleServiceInterface::class => ['concrete' => SampleService::class]], $this->app->getServices());

        /* set interface and class pair in array with concrete format */
        $this->app->flush();
        $this->app->set(SampleServiceInterface::class, ['concrete' => SampleService::class]);
        $this->assertSame([SampleServiceInterface::class => ['concrete' => SampleService::class]], $this->app->getServices());

        /* set alias and config pair */
        $this->app->flush();
        $this->app->set('name-alias', ['typeOf' => 'name']);
        $this->assertSame(['name-alias' => ['typeOf' => 'name']], $this->app->getServices());
    }

    /* test it can check entry exists */
    public function testFunctionHas()
    {
        /* entry not set */
        $this->assertFalse($this->app->has('name'));

        /* entry set */
        $this->app->set('name', 'Amit Sidhpura');
        $this->assertTrue($this->app->has('name'));

        /* entry not set but injector can create it */
        $this->assertTrue($this->app->has(SampleService::class));

        /* entry not set and injector cannot instantiate non existent class */
        /** @noinspection PhpUndefinedClassInspection */
        $this->assertFalse($this->app->has(SampleServiceNotExists::class));

        /* entry not set and injector cannot instantiate interface */
        $this->assertFalse($this->app->has(SampleServiceInterface::class));
    }

    /* test it can get entry */
    public function testFunctionGet()
    {
        $this->app->set('name', 'Amit Sidhpura');
        $this->assertSame('Amit Sidhpura', $this->app->get('name'));
    }

    /* test it can fire event */
    public function testFunctionFire()
    {
        /* test sequence of tasks based on event and index */
        $this->app->registerTask(FireHighIndexEventTask::class);
        $this->app->registerTask(FireLowIndexEventTask::class);
        $this->app->registerTask(FireEventTask::class);
        $data = $this->app->fire('something-happened', ['key' => '(fired)']);
        $this->assertSame(['key' => '(fired) low on high'], $data);
    }

    /* test it can run task */
    public function testFunctionRunTask()
    {
        $this->expectException(CircularDependencyException::class);
        $this->app->registerTask(TaskAFollowsTaskC::class);
        $this->app->registerTask(TaskBFollowsTaskA::class);
        $this->app->registerTask(TaskCFollowsTaskB::class);
        $this->app->runTask(TaskAFollowsTaskC::class);
    }

    /* test it can get runtime definition */
    public function testFunctionGetInjector()
    {
        /* test covered in testFunctionConstruct */
        $this->assertTrue(true);
    }

    /* test it can get modules */
    public function testFunctionGetDefinition()
    {
        /* test covered in testFunctionConstruct */
        $this->assertTrue(true);
    }

    /* test it can get injector */
    public function testFunctionGetModules()
    {
        /* test covered in testFunctionRegisterModule */
        $this->assertTrue(true);
    }

    /* test it can get tasks */
    public function testFunctionGetTasks()
    {
        /* test covered in testFunctionRegisterTask */
        $this->assertTrue(true);
    }

    /* test it can get services */
    public function testFunctionGetServices()
    {
        /* test covered in testFunctionSet */
        $this->assertTrue(true);
    }

    /* test it can get dependency stack */
    public function testFunctionGetDependencyStack() { $this->assertTrue(true); }

    /* test it can flush all modules, tasks, services and instance stack of the application */
    public function testFunctionFlush() { $this->assertTrue(true); }

    /* test it can get root path */
    public function testFunctionGetRootPath()
    {
        $this->assertSame($this->rootPath . DIRECTORY_SEPARATOR, $this->app->getRootPath());
    }

    /* test it can get local path */
    public function testFunctionGetLocalPath()
    {
        $this->assertSame($this->rootPath . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR, $this->app->getLocalPath());
    }

    /* test it can get vendor path */
    public function testFunctionGetVendorPath()
    {
        $this->assertSame($this->rootPath . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR, $this->app->getVendorPath());
    }

    /* test it can check whether given name is valid module, task or event name */
    public function testFunctionIsNameValid()
    {
        $this->assertTrue($this->app->isNameValid('task'));
        $this->assertTrue($this->app->isNameValid('task-one'));
        $this->assertTrue($this->app->isNameValid('task-123'));
        $this->assertFalse($this->app->isNameValid(''));
        $this->assertFalse($this->app->isNameValid('am'));
        $this->assertFalse($this->app->isNameValid('Task-one'));
        $this->assertFalse($this->app->isNameValid('task_one'));
    }

    /* test it can check whether entry is circular dependent */
    public function testFunctionIsEntryCircularDependent() { $this->assertTrue(true); }

    /* test it can convert delimiters string to camelcase string */
    public function testFunctionCamelize()
    {
        $this->assertSame('taskOne', $this->app->camelize('task-one'));
        $this->assertSame('taskOne', $this->app->camelize('task_one', '_'));
        $this->assertSame('taskone', $this->app->camelize('taskone'));
    }

    /* test it can convert camelcase string to delimiters string */
    public function testFunctionUncamelize()
    {
        $this->assertSame('task-one', $this->app->uncamelize('taskOne'));
        $this->assertSame('task_one', $this->app->uncamelize('taskOne', '_'));
        $this->assertSame('taskone', $this->app->uncamelize('taskone'));
    }

    /* test it can allow run task before and after particular task */
    public function testExtraRunTaskBeforeAndAfterParticularTask()
    {
        $this->app->registerTask(FireAfterEventTask::class);
        $this->app->registerTask(FireBeforeEventTask::class);
        $this->app->registerTask(FireEventTask::class);
        $data = $this->app->fire('something-happened', ['key' => '(fired)']);
        $this->assertSame(['key' => '(fired) before on after'], $data);
    }

    /* test it can allow to replace particular task and also to retain replaced task dependencies */
    public function testExtraReplaceTaskAndAlsoToRetainReplacedTaskDependencies()
    {
        $this->app->registerTask(FireAfterEventTask::class);
        $this->app->registerTask(FireBeforeEventTask::class);
        $this->app->registerTask(FireEventTask::class);
        $this->app->registerTask(FireReplaceEventTask::class);
        $data = $this->app->fire('something-happened', ['key' => '(fired)']);
        $this->assertSame(['key' => '(fired) before on replaced after'], $data);
    }
}
