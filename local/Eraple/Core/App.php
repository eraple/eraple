<?php

namespace Eraple\Core;

use Psr\Container\ContainerInterface;
use Eraple\Core\Exception\InvalidModuleException;
use Eraple\Core\Exception\InvalidTaskException;
use Eraple\Core\Exception\InvalidServiceException;
use Eraple\Core\Exception\InvalidEventException;
use Eraple\Core\Exception\CircularDependencyException;
use Eraple\Core\Exception\NotFoundException;
use Eraple\Core\Exception\MissingParameterException;

class App implements ContainerInterface
{
    /**
     * Application version.
     *
     * @var string
     */
    protected $version = '1.0.0';

    /**
     * Application root path.
     *
     * @var string
     */
    protected $rootPath = '';

    /**
     * Application instance.
     *
     * @var App
     */
    protected static $instance;

    /**
     * Application modules.
     *
     * @var array
     */
    protected $modules = ['byName' => []];

    /**
     * Application tasks.
     *
     * @var array
     */
    protected $tasks = ['byName' => [], 'byEvent' => []];

    /**
     * Application services.
     *
     * @var array
     */
    protected $services = [];

    /**
     * Application logs.
     *
     * @var array
     */
    protected $logs = [];

    /**
     * Application reflection classes and functions.
     *
     * @var \ReflectionClass[]|\ReflectionFunction[]
     */
    protected $reflections = [];

    /**
     * Application dependency stack.
     *
     * @var array
     */
    protected $dependencyStack = [];

    /**
     * Application constructor.
     *
     * @param string $rootPath Root path of the application
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    public function __construct(string $rootPath = null)
    {
        $this->rootPath = $rootPath;
        $this->set(App::class, $this);
    }

    /**
     * Get the application global instance.
     *
     * @param string $rootPath Root path of the application
     *
     * @return App
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    public static function instance(string $rootPath = null)
    {
        if (is_null(self::$instance)) {
            self::$instance = new self($rootPath);
        }

        return self::$instance;
    }

    /**
     * Get the application version.
     *
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * Run the application.
     */
    public function run()
    {
        /* set app start log */
        $this->setLog('app', 'start');

        /* run application */
        $this->collectModules();
        $this->collectTasks();
        $this->collectServices();
        $this->fire('before:start');
        $this->fire('start');
        $this->fire('after:start');
        $this->fire('before:end');
        $this->fire('end');
        $this->fire('after:end');

        /* set app end log */
        $this->setLog('app', 'end');
    }

    /**
     * Collect modules from local and vendor paths and set it to the application.
     */
    protected function collectModules()
    {
        /* collect local and vendor modules */
        $localModules = glob($this->getLocalPath() . '*' . DIRECTORY_SEPARATOR . '*');
        $vendorModules = glob($this->getVendorPath() . '*' . DIRECTORY_SEPARATOR . '*');

        /* set modules */
        foreach (array_merge($localModules, $vendorModules) as $module) {
            $module = trim($module, '/\\') . DIRECTORY_SEPARATOR . 'Module.php';

            /* set module only if it is a valid eraple module */
            if (file_exists($module) && is_subclass_of($module = require_once $module, Module::class)) {
                $this->setModule($module);
            }
        }
    }

    /**
     * Collect tasks from modules and set it to the application.
     */
    protected function collectTasks()
    {
        /* set tasks */
        foreach ($this->getModules() as $module) {
            $tasks = $module::getTasks();

            /* set task only if it is valid task */
            foreach ($tasks as $task) {
                $this->setTask($task);
            }
        }
    }

    /**
     * Collect services from tasks and set it to the application.
     */
    protected function collectServices()
    {
        /* set services */
        foreach ($this->getTasks() as $task) {
            $services = $task::getServices();

            /* set service only if it is valid service */
            foreach ($services as $serviceId => $service) {
                $this->setService($serviceId, $service);
            }
        }
    }

    /**
     * Set a module to the application.
     *
     * @param string $class Module class
     *
     * @throws InvalidModuleException
     */
    public function setModule(string $class)
    {
        /* @var $class Module::class */
        if (!is_subclass_of($class, Module::class) || !$this->isNameValid($class::getName())) throw new InvalidModuleException(sprintf(
            'Module "%s" is invalid', $class
        ));

        /* set module set log */
        $this->setLog('module', $class);

        $this->modules['byName'][$class::getName()] = $class;
    }

    /**
     * Set a task to the application.
     *
     * @param string $class Task class
     *
     * @throws InvalidTaskException
     */
    public function setTask(string $class)
    {
        /* @var $class Task::class */
        if (!is_subclass_of($class, Task::class) || !$this->isNameValid($class::getName())) throw new InvalidTaskException(sprintf(
            'Task "%s" is invalid', $class
        ));

        /* set task set log */
        $this->setLog('task', $class);

        $this->tasks['byName'][$class::getName()] = $class;
        $this->tasks['byEvent'] = array_merge_recursive($this->tasks['byEvent'], array_fill_keys($class::getEvents(), [$class::getName() => $class]));
    }

    /**
     * Set a service to the application.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return $this
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    public function setService(string $id, $entry)
    {
        return $this->set($id, $entry);
    }

    /**
     * Set s log entry of particular type to the application.
     *
     * @param string $type Log type
     * @param string $entry Log entry
     */
    public function setLog(string $type, string $entry)
    {
        if (!key_exists($type, $this->logs)) $this->logs[$type] = [];

        $this->logs[$type][] = ['time' => date('Y-m-d H:i:s'), 'entry' => $entry];
    }

    /**
     * Check whether service is set to the application.
     *
     * @param string $id Id of the service
     *
     * @return bool
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    public function has($id)
    {
        /* fire has event */
        extract($this->fire('has', ['id' => $id]));

        /* check has service */
        $has = key_exists($id, $this->services) || (class_exists($id) && !interface_exists($id));

        /* fire has event */
        extract($this->fire('has:result', ['id' => $id, 'has' => $has]));

        return $has;
    }

    /**
     * Set a service to the application.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return $this
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    public function set(string $id, $entry)
    {
        /* fire set event */
        extract($this->fire('set', ['id' => $id, 'entry' => $entry]));

        /* check whether service is valid */
        $valid = false;
        $serviceTypes = ['KeyValue', 'KeyConfig', 'ClassInstance', 'ClassConfig', 'InterfaceInstance', 'InterfaceClass', 'InterfaceConfig', 'AliasConfig'];
        foreach ($serviceTypes as $serviceType) if ($this->{'is' . $serviceType . 'Pair'}($id, $entry)) {
            $valid = true;
            break;
        }

        /* throw exception if entry not valid */
        if (!$valid) throw new InvalidServiceException(sprintf('Service with id "%s" is invalid', $id));

        /* set service set log */
        $this->setLog('service', $id);

        /* set service to the application */
        $this->services[$id] = $entry;

        return $this;
    }

    /**
     * Check whether service is of type key-value pair.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return bool
     */
    protected function isKeyValuePair(string $id, $entry)
    {
        if (!class_exists($id) && !interface_exists($id)
            && (!is_array($entry) || (!key_exists('alias', $entry) && !key_exists('instance', $entry)))) {
            return true;
        }

        return false;
    }

    /**
     * Check whether service is of type key-config pair.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return bool
     */
    protected function isKeyConfigPair(string $id, $entry)
    {
        if (is_string($id) && is_array($entry) && key_exists('instance', $entry)) {
            return true;
        }

        return false;
    }

    /**
     * Check whether service is of type class-instance pair.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return bool
     */
    protected function isClassInstancePair(string $id, $entry)
    {
        if (class_exists($id) && is_object($entry) && $entry instanceof $id) {
            return true;
        }

        return false;
    }

    /**
     * Check whether service is of type class-config pair.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return bool
     */
    protected function isClassConfigPair(string $id, $entry)
    {
        if (class_exists($id) && (is_array($entry) || is_null($entry))) {
            return true;
        }

        return false;
    }

    /**
     * Check whether service is of type interface-instance pair.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return bool
     */
    protected function isInterfaceInstancePair(string $id, $entry)
    {
        if (interface_exists($id) && is_object($entry) && $entry instanceof $id) {
            return true;
        }

        return false;
    }

    /**
     * Check whether service is of type interface-class pair.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return bool
     */
    protected function isInterfaceClassPair(string $id, $entry)
    {
        if (interface_exists($id) && is_string($entry) && class_exists($entry)) {
            return true;
        }

        return false;
    }

    /**
     * Check whether service is of type interface-config pair.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return bool
     */
    protected function isInterfaceConfigPair(string $id, $entry)
    {
        if (interface_exists($id) && is_array($entry) && key_exists('class', $entry) && class_exists($entry['class'])) {
            return true;
        }

        return false;
    }

    /**
     * Check whether service is of type alias-config pair.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return bool
     */
    protected function isAliasConfigPair(string $id, $entry)
    {
        if (!class_exists($id) && !interface_exists($id) && is_array($entry) && key_exists('alias', $entry)) {
            return true;
        }

        return false;
    }

    /**
     * Get an instance of service.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return mixed
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    public function get($id, $entry = null)
    {
        /* fire get event */
        extract($this->fire('get', ['id' => $id, 'entry' => $entry]));

        /* add stack entry */
        $this->checkCircularDependency('instance', $id);

        /* set get before log */
        $this->setLog('get', 'before:' . $id);

        /* entry not found and not instantiable */
        if (!$this->has($id) && is_null($entry)) throw new NotFoundException(sprintf('Service with id "%s" not found', $id));

        /* overwrite set entry with arg entry */
        $override = !is_null($entry) && key_exists($id, $this->services);
        if ($override && is_array($entry) && is_array($this->services[$id])) $entry = array_merge($this->services[$id], $entry);
        elseif (!$override && key_exists($id, $this->services)) $entry = $this->services[$id];

        /* check whether service is valid */
        $instance = null;
        $serviceTypes = ['KeyValue', 'KeyConfig', 'ClassInstance', 'ClassConfig', 'InterfaceInstance', 'InterfaceClass', 'InterfaceConfig', 'AliasConfig'];
        foreach ($serviceTypes as $serviceType) if ($this->{'is' . $serviceType . 'Pair'}($id, $entry)) {
            $instance = $this->{'getBy' . $serviceType . 'Pair'}($id, $entry);
            break;
        }

        /* if entry is not null and prepared entry is null */
        if (is_null($instance)) throw new InvalidServiceException(sprintf('Service with id "%s" is invalid', $id));

        /* set get after log */
        $this->setLog('get', 'after:' . $id);

        /* remove stack entry */
        array_pop($this->dependencyStack['instance']);

        /* fire get:instance event */
        extract($this->fire('get:instance', ['id' => $id, 'entry' => $entry, 'instance' => $instance]));

        return $instance;
    }

    /**
     * Get an instance of key-value pair type service.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return mixed
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    protected function getByKeyValuePair(string $id, $entry)
    {
        if ($entry instanceof \Closure) {
            return $this->runMethod($id, $entry, [], []);
        }

        return $entry;
    }

    /**
     * Get an instance of key-config pair type service.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return mixed
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    protected function getByKeyConfigPair(string $id, $entry)
    {
        if ($entry['instance'] instanceof \Closure) {
            $services = key_exists('services', $entry) ? $entry['services'] : [];
            $parameters = key_exists('parameters', $entry) ? $entry['parameters'] : [];

            return $this->runMethod($id, $entry['instance'], $services, $parameters);
        }

        return $entry['instance'];
    }

    /**
     * Get an instance of class-instance pair type service.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return mixed
     */
    protected function getByClassInstancePair(string $id, $entry)
    {
        return $entry;
    }

    /**
     * Get an instance of class-config pair type service.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return mixed
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    protected function getByClassConfigPair(string $id, $entry)
    {
        /* if entry is null */
        $entry = is_null($entry) ? [] : $entry;

        /* prepare services and parameters */
        $services = key_exists('services', $entry) ? $entry['services'] : [];
        $parameters = key_exists('parameters', $entry) ? $entry['parameters'] : [];

        /* create class instance with parameters */
        $instance = $this->runMethod($id, '__construct', $services, $parameters);

        /* save instance to services if singleton is true */
        $singleton = key_exists('singleton', $entry) ? $entry['singleton'] : false;
        if (key_exists($id, $this->services) && $singleton === true) $this->services[$id]['instance'] = $instance;

        return $instance;
    }

    /**
     * Get an instance of interface-instance pair type service.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return mixed
     */
    protected function getByInterfaceInstancePair(string $id, $entry)
    {
        return $entry;
    }

    /**
     * Get an instance of interface-class pair type service.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return mixed
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    protected function getByInterfaceClassPair(string $id, $entry)
    {
        return $this->get($entry);
    }

    /**
     * Get an instance of interface-config pair type service.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return mixed
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    protected function getByInterfaceConfigPair(string $id, $entry)
    {
        /* unset class and singleton keys */
        $class = $entry['class'];
        unset($entry['class']);
        $singleton = key_exists('singleton', $entry) ? $entry['singleton'] : false;
        unset($entry['singleton']);
        $entry = count($entry) ? $entry : null;

        /* create class instance with parameters */
        $instance = $this->get($class, $entry);

        /* save instance to services if singleton is true */
        if (key_exists($id, $this->services) && $singleton === true) $this->services[$id]['instance'] = $instance;

        return $instance;
    }

    /**
     * Get an instance of alias-config pair type service.
     *
     * @param string $id Id of the service
     * @param  mixed $entry Entry of the service
     *
     * @return mixed
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    protected function getByAliasConfigPair(string $id, $entry)
    {
        /* unset alias keys */
        $class = $entry['alias'];
        unset($entry['alias']);
        $singleton = key_exists('singleton', $entry) ? $entry['singleton'] : false;
        unset($entry['singleton']);
        $entry = count($entry) ? $entry : null;

        /* create class instance with parameters */
        $instance = $this->get($class, $entry);

        /* save instance to services if singleton is true */
        if (key_exists($id, $this->services) && $singleton === true) $this->services[$id]['instance'] = $instance;

        return $instance;
    }

    /**
     * Fire an event.
     *
     * @param string $event Name of the event
     * @param  array $data Data passed to the task
     *
     * @return array
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    public function fire(string $event, array $data = [])
    {
        /* set event before log */
        $this->setLog('event', 'before:' . $event);

        /* return if event name is not valid */
        if (!$this->isNameValid($event)) throw new InvalidEventException(sprintf('Event "%s" is invalid', $event));

        /* run tasks on event */
        foreach ($this->getTasks(['event' => $event], 'and', 'index') as $task) {
            $data = $this->runTask($task, $data);
        }

        /* set event after log */
        $this->setLog('event', 'after:' . $event);

        return $data;
    }

    /**
     * Run task along with related tasks.
     *
     * @param string $task Task to run
     * @param  array $data Data passed to the task
     *
     * @return array
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    public function runTask(string $task, array $data = [])
    {
        /* add stack entry */
        $this->checkCircularDependency('task', $task);

        /* replace task */
        /* @var $task Task::class */
        if (count($replacementTasks = $this->getTasks(['event' => 'replace:run-task:' . $task::getName()], 'and', 'index'))) {
            $data = $this->runTask(reset($replacementTasks), $data);
            /* remove stack entry */
            array_pop($this->dependencyStack['task']);

            return $data;
        }

        /* run tasks before task */
        $data = $this->fire('before:run-task', $data);
        $data = $this->fire('before:run-task:' . $task::getName(), $data);

        /* set task before log */
        $this->setLog('task', 'before:' . $task);

        /* run task */
        $returnData = $this->runMethod($task, 'run', [], ['data' => $data]);
        $data = is_array($returnData) ? $returnData : $data;

        /* set task after log */
        $this->setLog('task', 'after:' . $task);

        /* run tasks after task */
        $data = $this->fire('after:run-task', $data);
        $data = $this->fire('after:run-task:' . $task::getName(), $data);

        /* remove stack entry */
        array_pop($this->dependencyStack['task']);

        return $data;
    }

    /**
     * Get modules set to the application.
     *
     * @return Module[]
     */
    public function getModules()
    {
        return $this->modules['byName'];
    }

    /**
     * Get tasks set to the application.
     *
     * @param  array $filterBy Filter fields array
     * @param string $filterLogic Filter condition "and" or "or"
     * @param string $orderBy Order by field
     * @param string $order Ascending "asc" or descending "dsc" order
     *
     * @return Task[]
     */
    public function getTasks($filterBy = [], $filterLogic = 'and', $orderBy = null, $order = 'asc')
    {
        /* filter tasks */
        $name = key_exists('name', $filterBy) ? $filterBy['name'] : null;
        $event = key_exists('event', $filterBy) ? $filterBy['event'] : null;
        $tasksByName = $this->tasks['byName'];
        $tasksByEvent = $this->tasks['byEvent'];
        $isLogicAnd = strcasecmp(count($filterBy) ? $filterLogic : 'and', 'and') === 0;
        $tasksByLogic = $isLogicAnd ? $tasksByName : [];

        $tasksByName = !is_null($name) ? (key_exists($name, $tasksByName) ? [$name => $tasksByName[$name]] : []) : $tasksByLogic;
        $tasksByEvent = !is_null($event) ? (key_exists($event, $tasksByEvent) ? $tasksByEvent[$event] : []) : $tasksByLogic;

        $tasks = $isLogicAnd ? array_intersect_key($tasksByName, $tasksByEvent) : array_merge($tasksByName, $tasksByEvent);

        /* order tasks */
        if ($order !== null) {
            uasort($tasks, function (string $task1, string $task2) use ($orderBy, $order) {
                /* @var $task1 Task */
                /* @var $task2 Task */
                return $orderBy === 'name'
                    ? ($order === 'asc' ? $task1::getName() > $task2::getName() : $task1::getName() < $task2::getName())
                    : ($order === 'asc' ? $task1::getIndex() > $task2::getIndex() : $task1::getIndex() < $task2::getIndex());
            });
        }

        return $tasks;
    }

    /**
     * Get services set to the application.
     *
     * @return array
     */
    public function getServices()
    {
        return $this->services;
    }

    /**
     * Get logs set to the application.
     */
    public function getLogs()
    {
        return $this->logs;
    }

    /**
     * Get reflection classes and functions set to the application.
     *
     * @return \ReflectionClass[]|\ReflectionFunction[]
     */
    public function getReflections()
    {
        return $this->reflections;
    }

    /**
     * Get dependency stack set to the application.
     *
     * @return array
     */
    public function getDependencyStack()
    {
        return $this->dependencyStack;
    }

    /**
     * Get root path of the application.
     *
     * @return string
     */
    public function getRootPath()
    {
        return trim($this->rootPath, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Get local path of the application.
     *
     * @return string
     */
    public function getLocalPath()
    {
        return $this->getRootPath() . 'local' . DIRECTORY_SEPARATOR;
    }

    /**
     * Get vendor path of the application.
     *
     * @return string
     */
    public function getVendorPath()
    {
        return $this->getRootPath() . 'vendor' . DIRECTORY_SEPARATOR;
    }

    /**
     * Run class method or closure by injecting dependencies.
     *
     * @param string $id Id of the service
     * @param  mixed $method Class method or closure to run
     * @param  array $services Services to inject
     * @param  array $parameters Parameters to inject
     *
     * @return mixed|null
     *
     * @throws InvalidServiceException
     * @throws InvalidEventException
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws \ReflectionException
     */
    public function runMethod(string $id, $method = '__construct', array $services = [], array $parameters = [])
    {
        /* if method is not string and not closure return null */
        if (!is_string($method) && !$method instanceof \Closure) return null;

        /* if method is string and id is not class return null */
        if (is_string($method) && !class_exists($id)) return null;

        /* check if method is constructor */
        $isMethodConstructor = is_string($method) && strcmp($method, '__construct') === 0;

        /* if id is class and reflection not set set it */
        if (is_string($method) && !key_exists($id, $this->reflections)) $this->reflections[$id] = new \ReflectionClass($id);

        /* if method is closure and reflection not set set it */
        if (!is_string($method) && !key_exists($id, $this->reflections)) $this->reflections[$id] = new \ReflectionFunction($method);

        /* if method is constructor and constructor does not exist return instance without parameters */
        if ($isMethodConstructor && is_null($this->reflections[$id]->getConstructor())) return new $id();

        /* get reflection method */
        $reflectionMethod = is_string($method) ? $this->reflections[$id]->getMethod($method) : $this->reflections[$id];

        /* resolve parameters */
        $classParameters = $reflectionMethod->getParameters();
        foreach ($classParameters as $classParameter) {
            $classParameterName = $classParameter->getName();
            $classParameterType = $classParameter->getType();

            /* if parameter is passed as argument */
            if (key_exists($classParameterName, $parameters)) continue;

            /* if parameter is not passed as argument and parameter type is null */
            if (is_null($classParameterType)) throw new MissingParameterException(sprintf(
                'Parameter "%s" of method %s in id %s is missing', $classParameterName, is_string($method) ? $method : 'closure', $id
            ));

            $classParameterType = $classParameter->getType()->getName();

            /* if parameter can be resolved from container based on its type */
            try {
                if (key_exists($classParameterType, $services)) $parameter = $this->get($classParameterType, $services[$classParameterType]);
                else $parameter = $this->get($classParameterType);
            } catch (NotFoundException $notFoundException) {
                throw new MissingParameterException(sprintf(
                    'Parameter "%s" of method %s in id %s is missing', $classParameterName, is_string($method) ? $method : 'closure', $id
                ));
            }

            $parameters[$classParameterName] = $parameter;
        }

        /* arrange parameters in sequence method accepts */
        $classParametersNames = array_map(function (\ReflectionParameter $parameter) { return $parameter->getName(); }, $classParameters);
        $parameters = array_values(array_merge(array_flip($classParametersNames), $parameters));

        /* return output */
        if ($isMethodConstructor) return new $id(...$parameters);
        if (!is_string($method)) return $method(...$parameters);

        return $this->get($id)->$method(...$parameters);
    }

    /**
     * Check if there is circular dependency.
     *
     * @param string $type Stack type
     * @param string $entry Entry to check
     *
     * @throws CircularDependencyException
     */
    public function checkCircularDependency(string $type, string $entry)
    {
        if (!key_exists($type, $this->dependencyStack)) $this->dependencyStack[$type] = [];

        if (in_array($entry, $this->dependencyStack[$type])) {
            throw new CircularDependencyException(
                sprintf('Circular dependency in stack "%s": %s -> %s', $type, implode(' -> ', $this->dependencyStack[$type]), $entry)
            );
        } else {
            $this->dependencyStack[$type][] = $entry;
        }
    }

    /**
     * Flush the application instance.
     */
    public function flush()
    {
        $this->modules = ['byName' => []];
        $this->tasks = ['byName' => [], 'byEvent' => []];
        $this->services = [];
        $this->logs = [];
        $this->reflections = [];
        $this->dependencyStack = [];
    }

    /**
     * Check whether name is a valid module, task or event name.
     *
     * @param string $name Module, task or event name
     *
     * @return bool
     */
    public function isNameValid(string $name)
    {
        if (!empty($name) && preg_match('/^[0-9a-z-:]{3,255}$/', $name)) {
            return true;
        }

        return false;
    }

    /**
     * Convert full class path to valid name.
     *
     * @param string $class Class path
     *
     * @return string
     */
    public function classToName(string $class)
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9-]+/', '-', $class));
    }

    /**
     * Convert delimiters string to camelcase string.
     *
     * @param string $string String to convert to camelcase
     * @param string $delimiter Delimiter to replace
     *
     * @return string
     */
    public function camelize(string $string, string $delimiter = '-')
    {
        return lcfirst(str_replace($delimiter, '', ucwords($string, $delimiter)));
    }

    /**
     * Convert camelcase string to delimiters string.
     *
     * @param string $string String to convert to delimiter string
     * @param string $delimiter Delimiter to use
     *
     * @return string
     */
    public function uncamelize(string $string, string $delimiter = '-')
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '\\1' . $delimiter . '\\2', $string));
    }
}
