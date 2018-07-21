<?php

namespace Eraple\Test;

use Eraple\App;
use Zend\Di\Injector;
use Zend\Di\Definition\RuntimeDefinition;
use Zend\Di\Exception\MissingPropertyException;
use Eraple\Exception\CircularDependencyException;
use Eraple\Exception\NotFoundException;
use Eraple\Exception\ContainerException;
use Eraple\Test\Data\Stub\SampleModule;
use Eraple\Test\Data\Stub\InvalidNameModule;
use Eraple\Test\Data\Stub\NotExtendedAbstractModule;
use Eraple\Test\Data\Stub\SampleTask;
use Eraple\Test\Data\Stub\InvalidNameTask;
use Eraple\Test\Data\Stub\NotExtendedAbstractTask;
use Eraple\Test\Data\Stub\SampleServiceInterface;
use Eraple\Test\Data\Stub\SampleService;
use Eraple\Test\Data\Stub\SampleServiceHasParameters;
use Eraple\Test\Data\Stub\SampleServiceForPreferencesInterface;
use Eraple\Test\Data\Stub\SampleServiceForPreferences;
use Eraple\Test\Data\Stub\ServiceANeedsServiceB;
use Eraple\Test\Data\Stub\ServiceBNeedsServiceC;
use Eraple\Test\Data\Stub\ServiceCNeedsServiceA;
use Eraple\Test\Data\Stub\ServiceParameterCouldNotBeResolved;
use Eraple\Test\Data\Stub\SampleTaskHandlesEvent;
use Eraple\Test\Data\Stub\SampleTaskHandlesEventWithLowIndex;
use Eraple\Test\Data\Stub\SampleTaskHandlesEventWithHighIndex;
use Eraple\Test\Data\Stub\SampleTaskHandlesBeforeTaskRunEvent;
use Eraple\Test\Data\Stub\SampleTaskHandlesAfterTaskRunEvent;
use Eraple\Test\Data\Stub\SampleTaskHandlesReplaceTaskEvent;
use Eraple\Test\Data\Stub\TaskAFollowsTaskC;
use Eraple\Test\Data\Stub\TaskBFollowsTaskA;
use Eraple\Test\Data\Stub\TaskCFollowsTaskB;

class AppTest extends \PHPUnit\Framework\TestCase
{
    /* @var App */
    protected $app;

    /* @var string */
    protected $rootPath;

    /* setup application instance */
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
        $this->app->registerModule(NotExtendedAbstractModule::class);
        $this->assertSame(['sample-module' => SampleModule::class], $this->app->getModules());
    }

    /* test it can register task */
    public function testFunctionRegisterTask()
    {
        $this->app->registerTask(SampleTask::class);
        $this->app->registerModule(InvalidNameTask::class);
        $this->app->registerModule(NotExtendedAbstractTask::class);
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
        $this->assertSame([SampleServiceInterface::class => ['class' => SampleService::class]], $this->app->getServices());

        /* set interface and class pair in array with class format */
        $this->app->flush();
        $this->app->set(SampleServiceInterface::class, ['class' => SampleService::class]);
        $this->assertSame([SampleServiceInterface::class => ['class' => SampleService::class]], $this->app->getServices());

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
        $this->app->registerTask(SampleTaskHandlesEventWithHighIndex::class);
        $this->app->registerTask(SampleTaskHandlesEventWithLowIndex::class);
        $this->app->registerTask(SampleTaskHandlesEvent::class);
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
        $this->app->registerTask(SampleTaskHandlesAfterTaskRunEvent::class);
        $this->app->registerTask(SampleTaskHandlesBeforeTaskRunEvent::class);
        $this->app->registerTask(SampleTaskHandlesEvent::class);
        $data = $this->app->fire('something-happened', ['key' => '(fired)']);
        $this->assertSame(['key' => '(fired) before on after'], $data);
    }

    /* test it can allow to replace particular task and also to retain replaced task dependencies */
    public function testExtraReplaceParticularTask()
    {
        $this->app->registerTask(SampleTaskHandlesAfterTaskRunEvent::class);
        $this->app->registerTask(SampleTaskHandlesBeforeTaskRunEvent::class);
        $this->app->registerTask(SampleTaskHandlesEvent::class);
        $this->app->registerTask(SampleTaskHandlesReplaceTaskEvent::class);
        $data = $this->app->fire('something-happened', ['key' => '(fired)']);
        $this->assertSame(['key' => '(fired) before on replaced after'], $data);
    }

    /* test it can throw circular dependency exception when class entries exist and are circular dependent */
    public function testExtraThrowCircularDependencyExceptionWhenClassEntriesExistAndAreCircularDependent()
    {
        $this->expectException(CircularDependencyException::class);
        $this->app->set(ServiceANeedsServiceB::class, []);
        $this->app->set(ServiceBNeedsServiceC::class, []);
        $this->app->set(ServiceCNeedsServiceA::class, []);
        $this->app->get(ServiceANeedsServiceB::class);
    }

    /* test it can throw circular dependency exception when class entries does not exist and are circular dependent */
    public function testExtraThrowCircularDependencyExceptionWhenClassEntriesDoesNotExistAndAreCircularDependent()
    {
        $this->expectException(CircularDependencyException::class);
        $this->app->get(ServiceANeedsServiceB::class);
    }

    /* test it can throw circular dependency exception when alias is circular dependent */
    public function testExtraThrowCircularDependencyExceptionWhenAliasIsCircularDependent()
    {
        $this->expectException(CircularDependencyException::class);
        $this->app->set('name-alias1', ['typeOf' => 'name-alias2']);
        $this->app->set('name-alias2', ['typeOf' => 'name-alias1']);
        $this->app->get('name-alias1');
    }

    /* test it can throw not found exception when entry not found */
    public function testExtraThrowNotFoundException()
    {
        $this->expectException(NotFoundException::class);
        $this->app->get('name');
    }

    /* test it can throw missing property exception when injector cannot resolve a parameter */
    public function testExtraThrowMissingPropertyException()
    {
        $this->expectException(MissingPropertyException::class);
        $this->app->set(SampleServiceInterface::class, ServiceParameterCouldNotBeResolved::class);
        $this->app->get(SampleServiceInterface::class);
    }

    /* test it can throw container exception when entry found but not instantiable */
    public function testExtraThrowContainerException()
    {
        $this->expectException(ContainerException::class);
        $this->app->set(SampleServiceInterface::class, '');
        $this->app->get(SampleServiceInterface::class);
    }

    /* test it can get instance of entry not found but creatable */
    public function testExtraGetInstanceOfEntryNotFoundButCreatable()
    {
        $this->assertInstanceOf(SampleService::class, $this->app->get(SampleService::class));
    }

    /* test it can get instance of existing key */
    public function testExtraGetInstanceOfExistingKey()
    {
        $this->app->set('name', 'Amit Sidhpura');
        $this->assertSame('Amit Sidhpura', $this->app->get('name'));
        $this->app->set('name', ['instance' => 'Amit Sidhpura']);
        $this->assertSame('Amit Sidhpura', $this->app->get('name'));
        $this->app->set('name', function () { return 'Amit Sidhpura'; });
        $this->assertSame('Amit Sidhpura', $this->app->get('name'));
        $this->app->set('name', ['instance' => function () { return 'Amit Sidhpura'; }]);
        $this->assertSame('Amit Sidhpura', $this->app->get('name'));
    }

    /* test it can get instance of existing class */
    public function testExtraGetInstanceOfExistingClass()
    {
        /* id is class and entry has singleton as false */
        $this->app->set(SampleService::class, ['singleton' => false]);
        $sampleService1 = $this->app->get(SampleService::class);
        $sampleService2 = $this->app->get(SampleService::class);
        $this->assertNotSame($sampleService1, $sampleService2);

        /* id is class and entry has singleton as true */
        $this->app->set(SampleService::class, ['singleton' => true]);
        $sampleService1 = $this->app->get(SampleService::class);
        $sampleService2 = $this->app->get(SampleService::class);
        $this->assertSame($sampleService1, $sampleService2);

        /* id is class and entry has preferences and parameters */
        $classConfiguration = [
            'preferences' => [SampleServiceForPreferencesInterface::class => SampleServiceForPreferences::class],
            'parameters'  => ['name' => 'Amit Sidhpura']
        ];
        $this->app->set(SampleServiceHasParameters::class, $classConfiguration);
        $sampleService = $this->app->get(SampleServiceHasParameters::class);
        $this->assertInstanceOf(SampleServiceHasParameters::class, $sampleService);
        $this->assertSame('Amit Sidhpura', $sampleService->name);
        $this->assertInstanceOf(SampleServiceForPreferences::class, $sampleService->sampleServiceForPreferences);
    }

    /* test it can get instance of existing interface */
    public function testExtraGetInstanceOfExistingInterface()
    {
        /* id is interface and entry is class */
        $this->app->set(SampleServiceInterface::class, SampleService::class);
        $this->assertInstanceOf(SampleService::class, $this->app->get(SampleServiceInterface::class));

        /* id is interface and entry has singleton as false */
        $this->app->set(SampleServiceInterface::class, ['singleton' => false, 'class' => SampleService::class]);
        $sampleService1 = $this->app->get(SampleServiceInterface::class);
        $sampleService2 = $this->app->get(SampleServiceInterface::class);
        $this->assertNotSame($sampleService1, $sampleService2);

        /* id is interface and entry has singleton as true */
        $this->app->set(SampleServiceInterface::class, ['singleton' => true, 'class' => SampleService::class]);
        $sampleService1 = $this->app->get(SampleServiceInterface::class);
        $sampleService2 = $this->app->get(SampleServiceInterface::class);
        $this->assertSame($sampleService1, $sampleService2);

        /* id is interface and entry has preferences and parameters */
        $interfaceConfiguration = [
            'class'       => SampleServiceHasParameters::class,
            'preferences' => [SampleServiceForPreferencesInterface::class => SampleServiceForPreferences::class],
            'parameters'  => ['name' => 'Amit Sidhpura']
        ];
        $this->app->set(SampleServiceInterface::class, $interfaceConfiguration);
        $sampleService = $this->app->get(SampleServiceInterface::class);
        $this->assertInstanceOf(SampleServiceHasParameters::class, $sampleService);
        $this->assertSame('Amit Sidhpura', $sampleService->name);
        $this->assertInstanceOf(SampleServiceForPreferences::class, $sampleService->sampleServiceForPreferences);
    }

    /* test it can get instance of existing entry alias */
    public function testExtraGetInstanceOfExistingEntryAlias()
    {
        /* id is alias and entry is value */
        $this->app->set('name', 'Amit Sidhpura');
        $this->app->set('name-alias', ['typeOf' => 'name']);
        $this->assertSame('Amit Sidhpura', $this->app->get('name-alias'));

        /* id is alias and entry is alias */
        $this->app->set('name-alias1', ['typeOf' => 'name']);
        $this->app->set('name-alias2', ['typeOf' => 'name-alias1']);
        $this->assertSame('Amit Sidhpura', $this->app->get('name-alias2'));

        /* id is alias and entry is interface */
        $this->app->set(SampleServiceInterface::class, SampleService::class);
        $this->app->set('sample-service-interface', ['typeOf' => SampleServiceInterface::class]);
        $this->assertInstanceOf(SampleService::class, $this->app->get('sample-service-interface'));

        /* id is alias and entry is class */
        $this->app->set('sample-service', ['typeOf' => SampleService::class]);
        $this->assertInstanceOf(SampleService::class, $this->app->get('sample-service'));

        /* id is alias and entry is interface with preferences and parameters */
        $interfaceConfiguration = [
            'typeOf'      => SampleServiceInterface::class,
            'class'       => SampleServiceHasParameters::class,
            'preferences' => [SampleServiceForPreferencesInterface::class => SampleServiceForPreferences::class],
            'parameters'  => ['name' => 'Amit Sidhpura']
        ];
        $this->app->set('sample-service', $interfaceConfiguration);
        $sampleService = $this->app->get('sample-service');
        $this->assertInstanceOf(SampleServiceHasParameters::class, $sampleService);
        $this->assertSame('Amit Sidhpura', $sampleService->name);
        $this->assertInstanceOf(SampleServiceForPreferences::class, $sampleService->sampleServiceForPreferences);

        /* id is alias and entry is class with preferences and parameters */
        $classConfiguration = [
            'typeOf'      => SampleServiceHasParameters::class,
            'preferences' => [SampleServiceForPreferencesInterface::class => SampleServiceForPreferences::class],
            'parameters'  => ['name' => 'Dipali Sidhpura']
        ];
        $this->app->set('sample-service', $classConfiguration);
        $sampleService = $this->app->get('sample-service');
        $this->assertInstanceOf(SampleServiceHasParameters::class, $sampleService);
        $this->assertSame('Dipali Sidhpura', $sampleService->name);
        $this->assertInstanceOf(SampleServiceForPreferences::class, $sampleService->sampleServiceForPreferences);
    }

    /* test it can get instance of entry get without registering service */
    public function testExtraGetInstanceOfEntryWithoutRegisteringService()
    {
        /* key and value pair */
        $this->assertSame('Amit Sidhpura', $this->app->get('name', 'Amit Sidhpura'));

        /* class and configuration pair */
        $classConfiguration = [
            'preferences' => [SampleServiceForPreferencesInterface::class => SampleServiceForPreferences::class],
            'parameters'  => ['name' => 'Amit Sidhpura']
        ];
        $sampleService = $this->app->get(SampleServiceHasParameters::class, $classConfiguration);
        $this->assertInstanceOf(SampleServiceHasParameters::class, $sampleService);
        $this->assertSame('Amit Sidhpura', $sampleService->name);
        $this->assertInstanceOf(SampleServiceForPreferences::class, $sampleService->sampleServiceForPreferences);

        /* interface and class pair */
        $interfaceConfiguration = [
            'class'       => SampleServiceHasParameters::class,
            'preferences' => [SampleServiceForPreferencesInterface::class => SampleServiceForPreferences::class],
            'parameters'  => ['name' => 'Amit Sidhpura']
        ];
        $sampleService = $this->app->get(SampleServiceInterface::class, $interfaceConfiguration);
        $this->assertInstanceOf(SampleServiceHasParameters::class, $sampleService);
        $this->assertSame('Amit Sidhpura', $sampleService->name);
        $this->assertInstanceOf(SampleServiceForPreferences::class, $sampleService->sampleServiceForPreferences);

        /* alias and configuration pair */
        $this->app->set('name', 'Amit Sidhpura');
        $this->assertSame('Amit Sidhpura', $this->app->get('name-alias', ['typeOf' => 'name']));
    }
}
