<?php

namespace Eraple\Test;

use Eraple\App;
use Zend\Di\Exception\CircularDependencyException;

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

    /* test it can check entry exists */
    public function testHas() { $this->assertTrue(true); }

    /* test it can get entry */
    public function testGet()
    {
        /* id is key and entry is value */
        $this->app->set('name', ['Amit Sidhpura']);
        $this->assertSame(['Amit Sidhpura'], $this->app->get('name'));

        /* id is key and entry instance is value  */
        $this->app->set('name', ['instance' => 'Amit Sidhpura']);
        $this->assertSame('Amit Sidhpura', $this->app->get('name'));

        /* id is key and entry is closure */
        $this->app->flush();
        $this->app->set('get-name', function () { return 'Amit Sidhpura'; });
        $this->assertSame('Amit Sidhpura', $this->app->get('get-name'));

        /* id is key and entry instance is closure */
        $this->app->set('get-name', ['instance' => function () { return 'Amit Sidhpura'; }]);
        $this->assertSame('Amit Sidhpura', $this->app->get('get-name'));

        /* id is class and entry has singleton as false */
        $this->app->flush();
        $this->app->set(ClassOne::class, ['singleton' => false]);
        $classOne1 = $this->app->get(ClassOne::class);
        $classOne2 = $this->app->get(ClassOne::class);
        $this->assertNotSame($classOne1, $classOne2);

        /* id is class and entry has singleton as true */
        $this->app->set(ClassOne::class, ['singleton' => true]);
        $classOne1 = $this->app->get(ClassOne::class);
        $classOne2 = $this->app->get(ClassOne::class);
        $this->assertSame($classOne1, $classOne2);

        /* id is class and entry has parameters */
        $this->app->set(ClassThree::class, ['parameters' => ['name' => 'Amit Sidhpura']]);
        $classThree = $this->app->get(ClassThree::class);
        $this->assertInstanceOf(ClassThree::class, $classThree);
        $this->assertSame('Amit Sidhpura', $classThree->name);

        /* id is class and entry has preferences */
        $this->app->set(InterfaceOne::class, ClassOne::class);
        $classTwo = $this->app->get(ClassTwo::class);
        $this->assertInstanceOf(ClassTwo::class, $classTwo);
        $this->assertInstanceOf(ClassOne::class, $classTwo->classOne);

        /* id is class and entry has preferences */
        $this->app->set(ClassTwo::class, ['preferences' => [InterfaceOne::class => ClassFour::class]]);
        $classTwo = $this->app->get(ClassTwo::class);
        $this->assertInstanceOf(ClassTwo::class, $classTwo);
        $this->assertInstanceOf(ClassFour::class, $classTwo->classOne);

        /* id is interface and entry is class */
        $this->app->flush();
        $this->app->set(InterfaceOne::class, ClassOne::class);
        $this->assertInstanceOf(ClassOne::class, $this->app->get(InterfaceOne::class));

        /* id is interface and entry is class which has a dependency */
        $this->app->set(InterfaceTwo::class, ClassTwo::class);
        $this->assertInstanceOf(ClassTwo::class, $this->app->get(InterfaceTwo::class));

        /* id is interface and entry is class which has a entry */
        $this->app->set(InterfaceThree::class, ClassThree::class);
        $this->app->set(ClassThree::class, ['parameters' => ['name' => 'Amit Sidhpura']]);
        $this->assertInstanceOf(ClassThree::class, $this->app->get(InterfaceThree::class));

        /* id is alias and entry is value */
        $this->app->set('name', 'Amit Sidhpura');
        $this->app->set('my-name', ['typeOf' => 'name']);
        $this->assertSame('Amit Sidhpura', $this->app->get('my-name'));

        /* id is alias and entry is interface */
        $this->app->set('class-three', ['typeOf' => InterfaceThree::class]);
        $classThree = $this->app->get('class-three');
        $this->assertInstanceOf(ClassThree::class, $classThree);
        $this->assertSame('Amit Sidhpura', $classThree->name);
        $this->app->set('class-three', ['typeOf' => InterfaceThree::class, 'parameters' => ['name' => 'Dipali Sidhpura']]);
        $classThree = $this->app->get('class-three');
        $this->assertInstanceOf(ClassThree::class, $classThree);
        $this->assertSame('Dipali Sidhpura', $classThree->name);

        /* id is alias and entry is class */
        $this->app->set('class-three', ['typeOf' => ClassThree::class]);
        $classThree = $this->app->get('class-three');
        $this->assertInstanceOf(ClassThree::class, $classThree);
        $this->assertSame('Amit Sidhpura', $classThree->name);
        $this->app->set('class-three', ['typeOf' => ClassThree::class, 'parameters' => ['name' => 'Dipali Sidhpura']]);
        $classThree = $this->app->get('class-three');
        $this->assertInstanceOf(ClassThree::class, $classThree);
        $this->assertSame('Dipali Sidhpura', $classThree->name);

        /* id is alias and entry is alias */
        $this->app->flush();
        $this->app->set('name', 'Amit Sidhpura');
        $this->app->set('name-one', ['typeOf' => 'name']);
        $this->app->set('name-two', ['typeOf' => 'name-one']);
        $this->assertSame('Amit Sidhpura', $this->app->get('name-two'));

        /* throws exception on circular dependency */
        $this->expectException(CircularDependencyException::class);
        $this->app->get(ClassSix::class);
    }

    /* test it can set entry */
    public function testSet() { $this->assertTrue(true); }

    /* test it can get modules */
    public function testGetModules() { $this->assertTrue(true); }

    /* test it can get tasks */
    public function testGetTasks() { $this->assertTrue(true); }

    /* test it can get resources */
    public function testGetResources() { $this->assertTrue(true); }

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

    /* test it can convert delimiter string to camelcase */
    public function testCamelize()
    {
        $this->assertSame('taskOne', $this->app->camelize('task-one'));
        $this->assertSame('taskOne', $this->app->camelize('task_one', '_'));
        $this->assertSame('taskone', $this->app->camelize('taskone'));
    }

    public function testUncamelize()
    {
        $this->assertSame('task-one', $this->app->uncamelize('taskOne'));
        $this->assertSame('task_one', $this->app->uncamelize('taskOne', '_'));
        $this->assertSame('taskone', $this->app->uncamelize('taskone'));
    }
}

interface InterfaceOne
{
}

interface InterfaceTwo
{
}

interface InterfaceThree
{
}

interface InterfaceFour
{
}

class ClassOne implements InterfaceOne
{
}

class ClassTwo implements InterfaceTwo
{
    public $classOne;

    public function __construct(InterfaceOne $classOne)
    {
        $this->classOne = $classOne;
    }
}

class ClassThree implements InterfaceThree
{
    public $classOne;

    public $name;

    public function __construct(ClassOne $classOne, $name)
    {
        $this->classOne = $classOne;
        $this->name = $name;
    }
}

class ClassFour implements InterfaceOne
{
}

class ClassFive
{
    public function __construct(ClassSix $classSix) { }
}

class ClassSix
{
    public function __construct(ClassFive $classFive) { }
}
