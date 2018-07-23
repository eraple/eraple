<?php

namespace Eraple\Core\Test\Unit;

use Eraple\Core\App;
use Eraple\Core\Exception\CircularDependencyException;
use Eraple\Core\Exception\NotFoundException;
use Eraple\Core\Exception\MissingParameterException;
use Eraple\Core\Exception\ContainerException;
use Eraple\Core\Test\Unit\Data\App\Local\Eraple\Base\Module;
use Eraple\Core\Test\Unit\Data\App\Local\Eraple\Base\Task\TaskOne;
use Eraple\Core\Test\Unit\Data\App\Local\Eraple\Base\Task\TaskTwo;
use Eraple\Core\Test\Unit\Data\App\Local\Eraple\Base\Task\TaskThree;
use Eraple\Core\Test\Unit\Data\Stub\SampleModule;
use Eraple\Core\Test\Unit\Data\Stub\InvalidNameModule;
use Eraple\Core\Test\Unit\Data\Stub\NotExtendedAbstractModule;
use Eraple\Core\Test\Unit\Data\Stub\SampleTask;
use Eraple\Core\Test\Unit\Data\Stub\InvalidNameTask;
use Eraple\Core\Test\Unit\Data\Stub\NotExtendedAbstractTask;
use Eraple\Core\Test\Unit\Data\Stub\SampleServiceInterface;
use Eraple\Core\Test\Unit\Data\Stub\SampleService;
use Eraple\Core\Test\Unit\Data\Stub\SampleServiceHasParameters;
use Eraple\Core\Test\Unit\Data\Stub\SampleServiceForServicesArgumentInterface;
use Eraple\Core\Test\Unit\Data\Stub\SampleServiceForServicesArgument;
use Eraple\Core\Test\Unit\Data\Stub\ServiceANeedsServiceB;
use Eraple\Core\Test\Unit\Data\Stub\ServiceBNeedsServiceC;
use Eraple\Core\Test\Unit\Data\Stub\ServiceCNeedsServiceA;
use Eraple\Core\Test\Unit\Data\Stub\SampleTaskHandlesEvent;
use Eraple\Core\Test\Unit\Data\Stub\SampleTaskHandlesEventWithLowIndex;
use Eraple\Core\Test\Unit\Data\Stub\SampleTaskHandlesEventWithHighIndex;
use Eraple\Core\Test\Unit\Data\Stub\SampleTaskHandlesBeforeTaskRunEvent;
use Eraple\Core\Test\Unit\Data\Stub\SampleTaskHandlesAfterTaskRunEvent;
use Eraple\Core\Test\Unit\Data\Stub\SampleTaskHandlesReplaceTaskEvent;
use Eraple\Core\Test\Unit\Data\Stub\TaskAFollowsTaskC;
use Eraple\Core\Test\Unit\Data\Stub\TaskBFollowsTaskA;
use Eraple\Core\Test\Unit\Data\Stub\TaskCFollowsTaskB;

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

    /* test it can instantiate and set root path */
    public function testFunctionConstruct()
    {
        $app = new App('some_path_here');
        $this->assertSame('some_path_here' . DIRECTORY_SEPARATOR, $app->getRootPath());
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
    public function testFunctionRun()
    {
        $this->assertSame([], $this->app->getModules());
        $this->assertSame([], $this->app->getTasks());
        $this->assertSame([], array_diff_key($this->app->getServices(), [App::class => '']));
        $this->app->run();
        $modules = ['base' => Module::class];
        $this->assertSame($modules, $this->app->getModules());
        $tasks = ['task-one' => TaskOne::class, 'task-two' => TaskTwo::class, 'task-three' => TaskThree::class];
        $this->assertSame($tasks, $this->app->getTasks());
        $this->assertSame(['task-one' => TaskOne::class], $this->app->getTasks(['event' => 'start']));
        $this->assertSame(['task-two' => TaskTwo::class], $this->app->getTasks(['event' => 'before-end']));
        $this->assertSame(['task-three' => TaskThree::class], $this->app->getTasks(['event' => 'end']));
        $services = ['key-one' => ['instance' => 'value-one'], 'key-two' => ['instance' => 'value-two'], 'key-three' => ['instance' => 'value-three']];
        $this->assertSame($services, array_diff_key($this->app->getServices(), [App::class => '']));
    }

    /* test it can set module */
    public function testFunctionSetModule()
    {
        $this->app->setModule(SampleModule::class);
        $this->app->setModule(InvalidNameModule::class);
        $this->app->setModule(NotExtendedAbstractModule::class);
        $this->assertSame(['sample-module' => SampleModule::class], $this->app->getModules());
    }

    /* test it can set task */
    public function testFunctionSetTask()
    {
        $this->app->setTask(SampleTask::class);
        $this->app->setModule(InvalidNameTask::class);
        $this->app->setModule(NotExtendedAbstractTask::class);
        $this->assertSame(['sample-task' => SampleTask::class], $this->app->getTasks());
    }

    /* test it can set service */
    public function testFunctionSetService()
    {
        /* test covered in testFunctionSet */
        $this->assertTrue(true);
    }

    /* test it can set entry */
    public function testFunctionSet()
    {
        /* set name and value pair with invalid name */
        $this->app->set('My Name', 'Amit Sidhpura');
        $this->assertSame([], array_diff_key($this->app->getServices(), [App::class => '']));

        /* set name and value pair with valid name */
        $this->app->set('name', 'Amit Sidhpura');
        $this->assertSame(['name' => ['instance' => 'Amit Sidhpura']], array_diff_key($this->app->getServices(), [App::class => '']));

        /* set name and value pair in array with instance format */
        $this->app->flush();
        $this->app->set('name', ['instance' => 'Amit Sidhpura']);
        $this->assertSame(['name' => ['instance' => 'Amit Sidhpura']], $this->app->getServices());

        /* set name and value pair with value as array */
        $this->app->flush();
        $this->app->set('profile', ['first-name' => 'Amit', 'last-name' => 'Sidhpura']);
        $this->assertSame(['profile' => ['instance' => ['first-name' => 'Amit', 'last-name' => 'Sidhpura']]], $this->app->getServices());

        /* set class and instance pair */
        $this->app->flush();
        $instance = new SampleService();
        $this->app->set(SampleService::class, $instance);
        $this->assertSame([SampleService::class => ['instance' => $instance]], $this->app->getServices());

        /* set interface and class pair */
        $this->app->flush();
        $this->app->set(SampleServiceInterface::class, SampleService::class);
        $this->assertSame([SampleServiceInterface::class => ['class' => SampleService::class]], $this->app->getServices());

        /* set interface and class pair in array with class format */
        $this->app->flush();
        $this->app->set(SampleServiceInterface::class, ['class' => SampleService::class]);
        $this->assertSame([SampleServiceInterface::class => ['class' => SampleService::class]], $this->app->getServices());

        /* set interface abd object pair */
        $this->app->flush();
        $instance = new SampleService();
        $this->app->set(SampleServiceInterface::class, $instance);
        $this->assertSame([SampleServiceInterface::class => ['instance' => $instance]], $this->app->getServices());

        /* set alias and config pair */
        $this->app->flush();
        $this->app->set('name-alias', ['alias' => 'name']);
        $this->assertSame(['name-alias' => ['alias' => 'name']], $this->app->getServices());
    }

    /* test it can check entry exists */
    public function testFunctionHas()
    {
        /* entry not set */
        $this->assertFalse($this->app->has('name'));

        /* entry set */
        $this->app->set('name', 'Amit Sidhpura');
        $this->assertTrue($this->app->has('name'));

        /* entry not set but application can create it */
        $this->assertTrue($this->app->has(SampleService::class));

        /* entry not set and application cannot instantiate non existent class */
        /** @noinspection PhpUndefinedClassInspection */
        $this->assertFalse($this->app->has(SampleServiceNotExists::class));

        /* entry not set and application cannot instantiate interface */
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
        $this->app->setTask(SampleTaskHandlesEventWithHighIndex::class);
        $this->app->setTask(SampleTaskHandlesEventWithLowIndex::class);
        $this->app->setTask(SampleTaskHandlesEvent::class);
        $data = $this->app->fire('something-happened', ['key' => '(fired)']);
        $this->assertSame(['key' => '(fired) low on high'], $data);
    }

    /* test it can run task */
    public function testFunctionRunTask()
    {
        $this->expectException(CircularDependencyException::class);
        $this->app->setTask(TaskAFollowsTaskC::class);
        $this->app->setTask(TaskBFollowsTaskA::class);
        $this->app->setTask(TaskCFollowsTaskB::class);
        $this->app->runTask(TaskAFollowsTaskC::class);
    }

    /* test it can run method */
    public function testFunctionRunMethod()
    {
        /* method is not string or closure */
        $this->assertNull($this->app->runMethod('some-class', true));

        /* method is string and id not class */
        $this->assertNull($this->app->runMethod('not-class', 'some-method'));

        /* set reflection classes entry of the application */
        /* if method is constructor and constructor does not exist return instance of the class */
        $sampleService = $this->app->runMethod(SampleService::class);
        $reflectionClasses = $this->app->getReflectionClasses();
        $this->assertArrayHasKey(SampleService::class, $reflectionClasses);
        $this->assertInstanceOf(\ReflectionClass::class, $reflectionClasses[SampleService::class]);
        $this->assertInstanceOf(SampleService::class, $sampleService);

        /* set reflection classes function entry of the application */
        /* if method is closure with parameters return output by executing the function */
        $function = function (string $name, SampleServiceInterface $sampleService) { return ['name' => $name, 'sampleService' => $sampleService]; };
        $services = [SampleServiceInterface::class => SampleService::class];
        $parameters = ['name' => 'Amit Sidhpura'];
        $return = $this->app->runMethod('sample-closure', $function, $services, $parameters);
        $reflectionClasses = $this->app->getReflectionClasses();
        $this->assertArrayHasKey('sample-closure', $reflectionClasses);
        $this->assertInstanceOf(\ReflectionFunction::class, $reflectionClasses['sample-closure']);
        $this->assertSame('Amit Sidhpura', $return['name']);
        $this->assertInstanceOf(SampleService::class, $return['sampleService']);

        /* if method is constructor with parameters return instance of the class */
        $services = [SampleServiceForServicesArgumentInterface::class => SampleServiceForServicesArgument::class];
        $parameters = ['name' => 'Amit Sidhpura'];
        $sampleServiceHasParameters = $this->app->runMethod(SampleServiceHasParameters::class, '__construct', $services, $parameters);
        $this->assertInstanceOf(SampleServiceHasParameters::class, $sampleServiceHasParameters);
        $this->assertSame('Amit Sidhpura', $sampleServiceHasParameters->name);
        $this->assertInstanceOf(SampleServiceForServicesArgument::class, $sampleServiceHasParameters->sampleServiceForServicesArgument);

        /* if method is class method with parameters return output by executing the function */
        $this->app->set(SampleServiceHasParameters::class, ['services' => $services, 'parameters' => $parameters]);
        $return = $this->app->runMethod(SampleServiceHasParameters::class, 'methodHasParameters', $services, $parameters);
        $this->assertSame('Amit Sidhpura', $return['name']);
        $this->assertInstanceOf(SampleServiceForServicesArgument::class, $return['sampleServiceForServicesArgument']);

        /* throws parameter not found exception */
        $this->expectException(MissingParameterException::class);
        $parameters = [];
        $this->app->runMethod(SampleServiceHasParameters::class, 'methodHasParameters', $services, $parameters);
    }

    /* test it can get reflection classes */
    public function testFunctionGetReflectionClasses()
    {
        $this->app->runMethod(SampleService::class);
        $reflectionClasses = $this->app->getReflectionClasses();
        $this->assertArrayHasKey(SampleService::class, $reflectionClasses);
        $this->assertInstanceOf(\ReflectionClass::class, $reflectionClasses[SampleService::class]);
    }

    /* test it can get modules */
    public function testFunctionGetModules()
    {
        /* test covered in testFunctionSetModule */
        $this->assertTrue(true);
    }

    /* test it can get tasks */
    public function testFunctionGetTasks()
    {
        /* test covered in testFunctionSetTask */
        $this->assertTrue(true);
    }

    /* test it can get services */
    public function testFunctionGetServices()
    {
        /* test covered in testFunctionSet */
        $this->assertTrue(true);
    }

    /* test it can get dependency stack */
    public function testFunctionGetDependencyStack()
    {
        /* test covered in testFunctionIsEntryCircularDependent */
        $this->assertTrue(true);
    }

    /* test it can flush all modules, tasks, services and instance stack of the application */
    public function testFunctionFlush()
    {
        $this->app->setModule(SampleModule::class);
        $this->app->setTask(SampleTask::class);
        $this->app->setService('name', 'Amit Sidhpura');
        $this->app->isEntryCircularDependent('sample-stack', 'sample-entry');
        $this->assertSame(['sample-module' => SampleModule::class], $this->app->getModules());
        $this->assertSame(['sample-task' => SampleTask::class], $this->app->getTasks());
        $this->assertSame(['name' => ['instance' => 'Amit Sidhpura']], array_diff_key($this->app->getServices(), [App::class => '']));
        $this->assertSame(['sample-stack' => ['sample-entry']], $this->app->getDependencyStack());
        $this->app->flush();
        $this->assertSame([], $this->app->getModules());
        $this->assertSame([], $this->app->getTasks());
        $this->assertSame([], $this->app->getServices());
        $this->assertSame([], $this->app->getDependencyStack());
    }

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

    /* test it can check whether service is key-value pair */
    public function testFunctionIsServiceKeyValuePair()
    {
        $this->assertTrue($this->app->isServiceKeyValuePair('name', 'Amit Sidhpura'));
        $this->assertTrue($this->app->isServiceKeyValuePair('function', function () { }));
        $this->assertTrue($this->app->isServiceKeyValuePair('array', []));
        $this->assertTrue($this->app->isServiceKeyValuePair('object', new \stdClass()));
        $this->assertFalse($this->app->isServiceKeyValuePair(SampleService::class, ''));
        $this->assertFalse($this->app->isServiceKeyValuePair(SampleServiceInterface::class, ''));
        $this->assertFalse($this->app->isServiceKeyValuePair('alias', ['alias' => 'name']));
        $this->assertFalse($this->app->isServiceKeyValuePair('instance', ['instance' => 'Amit Sidhpura']));
    }

    /* test it can check whether service is key-config pair */
    public function testFunctionIsServiceKeyConfigPair()
    {
        $this->assertTrue($this->app->isServiceKeyConfigPair('instance', ['instance' => 'Amit Sidhpura']));
        $this->assertFalse($this->app->isServiceKeyConfigPair('instance', []));
        $this->assertFalse($this->app->isServiceKeyConfigPair('instance', 'Amit Sidhpura'));
    }

    /* test it can check whether service is class-config pair */
    public function testFunctionIsServiceClassConfigPair()
    {
        $this->assertTrue($this->app->isServiceClassConfigPair(SampleService::class, []));
        $this->assertFalse($this->app->isServiceClassConfigPair('name', []));
        $this->assertFalse($this->app->isServiceClassConfigPair(SampleService::class, ''));
    }

    /* test it can check whether service is class-instance pair */
    public function testFunctionIsServiceClassInstancePair()
    {
        $this->assertTrue($this->app->isServiceClassInstancePair(SampleService::class, new SampleService()));
        $this->assertFalse($this->app->isServiceClassInstancePair(SampleService::class, new SampleServiceForServicesArgument()));
    }

    /* test it can check whether service is interface-class pair */
    public function testFunctionIsServiceInterfaceClassPair()
    {
        $this->assertTrue($this->app->isServiceInterfaceClassPair(SampleServiceInterface::class, SampleService::class));
        $this->assertFalse($this->app->isServiceInterfaceClassPair('sample-service', SampleService::class));
        $this->assertFalse($this->app->isServiceInterfaceClassPair(SampleServiceInterface::class, []));
        $this->assertFalse($this->app->isServiceInterfaceClassPair(SampleServiceInterface::class, 'sample-service-interface'));
    }

    /* test it can check whether service is interface-config pair */
    public function testFunctionIsServiceInterfaceConfigPair()
    {
        $this->assertTrue($this->app->isServiceInterfaceConfigPair(SampleServiceInterface::class, ['class' => SampleService::class]));
        $this->assertFalse($this->app->isServiceInterfaceConfigPair('sample-service', ['class' => SampleService::class]));
        $this->assertFalse($this->app->isServiceInterfaceConfigPair(SampleServiceInterface::class, 'sample-service-interface'));
        $this->assertFalse($this->app->isServiceInterfaceConfigPair(SampleServiceInterface::class, []));
        $this->assertFalse($this->app->isServiceInterfaceConfigPair(SampleServiceInterface::class, ['class' => 'sample-service']));
    }

    /* test it can check whether service is interface-instance pair */
    public function testFunctionIsServiceInterfaceInstancePair()
    {
        $this->assertTrue($this->app->isServiceInterfaceInstancePair(SampleServiceInterface::class, new SampleService()));
        $this->assertFalse($this->app->isServiceInterfaceInstancePair(SampleServiceInterface::class, new SampleServiceForServicesArgument()));
    }

    /* test it can check whether service is alias-config pair */
    public function testFunctionIsServiceAliasConfigPair()
    {
        $this->assertTrue($this->app->isServiceAliasConfigPair('name-alias', ['alias' => 'name']));
        $this->assertFalse($this->app->isServiceAliasConfigPair(SampleService::class, ['alias' => 'name']));
        $this->assertFalse($this->app->isServiceAliasConfigPair(SampleServiceInterface::class, ['alias' => 'name']));
        $this->assertFalse($this->app->isServiceAliasConfigPair('name-alias', ''));
        $this->assertFalse($this->app->isServiceAliasConfigPair('name-alias', []));
    }

    /* test it can check whether entry is circular dependent */
    public function testFunctionIsEntryCircularDependent()
    {
        $this->app->isEntryCircularDependent('sample1-id', 'sample1-entry1');
        $this->app->isEntryCircularDependent('sample1-id', 'sample1-entry2');
        $this->app->isEntryCircularDependent('sample2-id', 'sample2-entry1');
        $this->app->isEntryCircularDependent('sample2-id', 'sample2-entry2');
        $dependencyStack = ['sample1-id' => ['sample1-entry1', 'sample1-entry2'], 'sample2-id' => ['sample2-entry1', 'sample2-entry2']];
        $this->assertSame($dependencyStack, $this->app->getDependencyStack());
        $this->expectException(CircularDependencyException::class);
        $this->app->isEntryCircularDependent('sample1-id', 'sample1-entry1');
    }

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
        $this->app->setTask(SampleTaskHandlesAfterTaskRunEvent::class);
        $this->app->setTask(SampleTaskHandlesBeforeTaskRunEvent::class);
        $this->app->setTask(SampleTaskHandlesEvent::class);
        $data = $this->app->fire('something-happened', ['key' => '(fired)']);
        $this->assertSame(['key' => '(fired) before on after'], $data);
    }

    /* test it can allow to replace particular task and also to retain replaced task dependencies */
    public function testExtraReplaceParticularTask()
    {
        $this->app->setTask(SampleTaskHandlesAfterTaskRunEvent::class);
        $this->app->setTask(SampleTaskHandlesBeforeTaskRunEvent::class);
        $this->app->setTask(SampleTaskHandlesEvent::class);
        $this->app->setTask(SampleTaskHandlesReplaceTaskEvent::class);
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
        $this->app->set('name-alias1', ['alias' => 'name-alias2']);
        $this->app->set('name-alias2', ['alias' => 'name-alias1']);
        $this->app->get('name-alias1');
    }

    /* test it can throw not found exception when entry not found */
    public function testExtraThrowNotFoundException()
    {
        $this->expectException(NotFoundException::class);
        $this->app->get('name');
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

        /* id is class and entry has services and parameters */
        $classConfiguration = [
            'services'   => [SampleServiceForServicesArgumentInterface::class => SampleServiceForServicesArgument::class],
            'parameters' => ['name' => 'Amit Sidhpura']
        ];
        $this->app->set(SampleServiceHasParameters::class, $classConfiguration);
        $sampleService = $this->app->get(SampleServiceHasParameters::class);
        $this->assertInstanceOf(SampleServiceHasParameters::class, $sampleService);
        $this->assertSame('Amit Sidhpura', $sampleService->name);
        $this->assertInstanceOf(SampleServiceForServicesArgument::class, $sampleService->sampleServiceForServicesArgument);
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

        /* id is interface and entry has services and parameters */
        $interfaceConfiguration = [
            'class'      => SampleServiceHasParameters::class,
            'services'   => [SampleServiceForServicesArgumentInterface::class => SampleServiceForServicesArgument::class],
            'parameters' => ['name' => 'Amit Sidhpura']
        ];
        $this->app->set(SampleServiceInterface::class, $interfaceConfiguration);
        $sampleService = $this->app->get(SampleServiceInterface::class);
        $this->assertInstanceOf(SampleServiceHasParameters::class, $sampleService);
        $this->assertSame('Amit Sidhpura', $sampleService->name);
        $this->assertInstanceOf(SampleServiceForServicesArgument::class, $sampleService->sampleServiceForServicesArgument);
    }

    /* test it can get instance of existing entry alias */
    public function testExtraGetInstanceOfExistingEntryAlias()
    {
        /* id is alias and entry is value */
        $this->app->set('name', 'Amit Sidhpura');
        $this->app->set('name-alias', ['alias' => 'name']);
        $this->assertSame('Amit Sidhpura', $this->app->get('name-alias'));

        /* id is alias and entry is alias */
        $this->app->set('name-alias1', ['alias' => 'name']);
        $this->app->set('name-alias2', ['alias' => 'name-alias1']);
        $this->assertSame('Amit Sidhpura', $this->app->get('name-alias2'));

        /* id is alias and entry is interface */
        $this->app->set(SampleServiceInterface::class, SampleService::class);
        $this->app->set('sample-service-interface', ['alias' => SampleServiceInterface::class]);
        $this->assertInstanceOf(SampleService::class, $this->app->get('sample-service-interface'));

        /* id is alias and entry is class */
        $this->app->set('sample-service', ['alias' => SampleService::class]);
        $this->assertInstanceOf(SampleService::class, $this->app->get('sample-service'));

        /* id is alias and entry is interface with services and parameters */
        $interfaceConfiguration = [
            'alias'      => SampleServiceInterface::class,
            'class'      => SampleServiceHasParameters::class,
            'services'   => [SampleServiceForServicesArgumentInterface::class => SampleServiceForServicesArgument::class],
            'parameters' => ['name' => 'Amit Sidhpura']
        ];
        $this->app->set('sample-service', $interfaceConfiguration);
        $sampleService = $this->app->get('sample-service');
        $this->assertInstanceOf(SampleServiceHasParameters::class, $sampleService);
        $this->assertSame('Amit Sidhpura', $sampleService->name);
        $this->assertInstanceOf(SampleServiceForServicesArgument::class, $sampleService->sampleServiceForServicesArgument);

        /* id is alias and entry is class with services and parameters */
        $classConfiguration = [
            'alias'      => SampleServiceHasParameters::class,
            'services'   => [SampleServiceForServicesArgumentInterface::class => SampleServiceForServicesArgument::class],
            'parameters' => ['name' => 'Dipali Sidhpura']
        ];
        $this->app->set('sample-service', $classConfiguration);
        $sampleService = $this->app->get('sample-service');
        $this->assertInstanceOf(SampleServiceHasParameters::class, $sampleService);
        $this->assertSame('Dipali Sidhpura', $sampleService->name);
        $this->assertInstanceOf(SampleServiceForServicesArgument::class, $sampleService->sampleServiceForServicesArgument);
    }

    /* test it can get instance of entry get without setting service */
    public function testExtraGetInstanceOfEntryWithoutSettingService()
    {
        /* key and value pair */
        $this->assertSame('Amit Sidhpura', $this->app->get('name', 'Amit Sidhpura'));

        /* class and configuration pair */
        $classConfiguration = [
            'services'   => [SampleServiceForServicesArgumentInterface::class => SampleServiceForServicesArgument::class],
            'parameters' => ['name' => 'Amit Sidhpura']
        ];
        $sampleService = $this->app->get(SampleServiceHasParameters::class, $classConfiguration);
        $this->assertInstanceOf(SampleServiceHasParameters::class, $sampleService);
        $this->assertSame('Amit Sidhpura', $sampleService->name);
        $this->assertInstanceOf(SampleServiceForServicesArgument::class, $sampleService->sampleServiceForServicesArgument);

        /* interface and class pair */
        $interfaceConfiguration = [
            'class'      => SampleServiceHasParameters::class,
            'services'   => [SampleServiceForServicesArgumentInterface::class => SampleServiceForServicesArgument::class],
            'parameters' => ['name' => 'Amit Sidhpura']
        ];
        $sampleService = $this->app->get(SampleServiceInterface::class, $interfaceConfiguration);
        $this->assertInstanceOf(SampleServiceHasParameters::class, $sampleService);
        $this->assertSame('Amit Sidhpura', $sampleService->name);
        $this->assertInstanceOf(SampleServiceForServicesArgument::class, $sampleService->sampleServiceForServicesArgument);

        /* alias and configuration pair */
        $this->app->set('name', 'Amit Sidhpura');
        $this->assertSame('Amit Sidhpura', $this->app->get('name-alias', ['alias' => 'name']));
    }
}
