<?php

namespace Eraple;

use Psr\Container\ContainerInterface;
use Eraple\Exception\CircularDependencyException;
use Eraple\Exception\NotFoundException;
use Eraple\Exception\ContainerException;

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
     * Registered reflection classes.
     *
     * @var \ReflectionClass[]
     */
    protected $reflectionClasses = [];

    /**
     * Registered modules.
     *
     * @var Module[]
     */
    protected $modules = [];

    /**
     * Registered tasks.
     *
     * @var Task[]
     */
    protected $tasks = [];

    /**
     * Registered services.
     *
     * @var array
     */
    protected $services = [];

    /**
     * Dependency stack.
     *
     * @var array
     */
    protected $dependencyStack = [];

    /**
     * Application constructor.
     *
     * @param string $rootPath Root path of the application
     */
    public function __construct(string $rootPath = null)
    {
        $this->rootPath = $rootPath;
    }

    /**
     * Get the application global instance.
     *
     * @param string $rootPath Root path of the application
     *
     * @return App
     */
    public static function instance(string $rootPath = null)
    {
        if (is_null(self::$instance)) {
            self::$instance = new self($rootPath);
        }

        return self::$instance;
    }

    /**
     * Get version of the application.
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
        $this->registerModules();
        $this->registerTasks();
        $this->registerServices();
        $this->fire('before-start');
        $this->fire('start');
        $this->fire('after-start');
        $this->fire('before-end');
        $this->fire('end');
        $this->fire('after-end');
    }

    /**
     * Register modules in local and vendor paths.
     */
    protected function registerModules()
    {
        /* collect local and vendor modules */
        $localModules = glob($this->getLocalPath() . '*' . DIRECTORY_SEPARATOR . '*');
        $vendorModules = glob($this->getVendorPath() . '*' . DIRECTORY_SEPARATOR . '*');

        /* register modules */
        foreach (array_merge($localModules, $vendorModules) as $module) {
            $module = trim($module, '/\\') . DIRECTORY_SEPARATOR . 'Module.php';

            /* register module only if it is a valid eraple module */
            if (file_exists($module) && is_subclass_of($module = require_once $module, Module::class)) {
                $this->setModule($module);
            }
        }
    }

    /**
     * Register module tasks.
     */
    protected function registerTasks()
    {
        /* register tasks */
        foreach ($this->modules as $module) {
            $tasks = $module::getTasks();
            foreach ($tasks as $task) {
                $this->setTask($task);
            }
        }
    }

    /**
     * Register services.
     */
    protected function registerServices()
    {
        /* register services */
        foreach ($this->tasks as $task) {
            $services = $task::getServices();

            foreach ($services as $serviceId => $service) {
                $this->set($serviceId, $service);
            }
        }
    }

    /**
     * Set a module to the application.
     *
     * @param string $class Module class
     */
    public function setModule(string $class)
    {
        /* @var $class Module::class */
        if (!is_subclass_of($class, Module::class) || !$this->isNameValid($class::getName())) return;

        $this->modules[$class::getName()] = $class;
    }

    /**
     * Set a task with the application.
     *
     * @param string $class Task class
     */
    public function setTask(string $class)
    {
        /* @var $class Task::class */
        if (!is_subclass_of($class, Task::class) || !$this->isNameValid($class::getName())) return;

        $this->tasks[$class::getName()] = $class;
    }

    /**
     * Set an entry to the application.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return $this
     */
    public function setService(string $id, $entry)
    {
        return $this->set($id, $entry);
    }

    /**
     * Set an entry to the application.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return $this
     */
    public function set(string $id, $entry)
    {
        $entry = $this->prepareServiceEntry($id, $entry);

        /* set service to the application */
        if (!is_null($entry)) $this->services[$id] = $entry;

        return $this;
    }

    /**
     * Prepare service before registering it to the application.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return array|null
     */
    protected function prepareServiceEntry(string $id, $entry)
    {
        /* check entry is null then return null */
        if (is_null($entry)) return null;

        /* discard entry with invalid name */
        if (!class_exists($id) && !interface_exists($id) && !$this->isNameValid($id)) return null;

        /* process entry with id as key and entry as value */
        if ($this->isServiceKeyValuePair($id, $entry)) $entry = ['instance' => $entry];

        /* process entry with id as interface and entry as class */
        if ($this->isServiceInterfaceClassPair($id, $entry)) $entry = ['class' => $entry];

        return $entry;
    }

    /**
     * Check whether an entry exists in the application.
     *
     * @param string $id Id of an entry
     *
     * @return bool
     */
    public function has($id)
    {
        if (isset($this->services[$id])) return true;

        return class_exists($id) && !interface_exists($id);
    }

    /**
     * Get an instance of the application service by its id.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return mixed
     * @throws CircularDependencyException|NotFoundException|ContainerException|\ReflectionException
     */
    public function get($id, $entry = null)
    {
        /* add stack entry */
        $this->isEntryCircularDependent('instance', $id);

        /* prepare service entry */
        $entry = $this->prepareServiceEntry($id, $entry);

        /* entry not found and not instantiable */
        if (!$this->has($id) && is_null($entry)) throw new NotFoundException('Id "' . $id . '" no found.');

        /* entry not found but instantiable */
        if (!isset($this->services[$id]) && is_null($entry)) {
            $instance = $this->runMethod($id);
            /* remove stack entry */
            array_pop($this->dependencyStack['instance']);

            return $instance;
        }

        /* entry found and instantiable */
        $functions = ['getEntryInstanceByIdKey', 'getEntryInstanceByIdClass', 'getEntryInstanceByIdInterface', 'getEntryInstanceByIdAlias'];
        $entry = !is_null($entry) ? $entry : $this->services[$id];
        $entry = is_array($entry) && isset($this->services[$id]) && is_array($this->services[$id]) ? array_merge($this->services[$id], $entry) : $entry;
        foreach ($functions as $function) {
            $instance = $this->$function($id, $entry);
            if (!is_null($instance)) {
                /* remove stack entry */
                array_pop($this->dependencyStack['instance']);

                return $instance;
            }
        }

        /* throw exception if entry not instantiable */
        throw new ContainerException();
    }

    /**
     * Get an entry instance of the application by its id key.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return null|mixed
     */
    protected function getEntryInstanceByIdKey(string $id, $entry)
    {
        if ($this->isServiceKeyConfigPair($id, $entry)) {
            if ($entry['instance'] instanceof \Closure) {
                return $entry['instance']($this);
            } else {
                return $entry['instance'];
            }
        }

        return null;
    }

    /**
     * Get an entry instance of the application by it id class.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return null|object
     * @throws CircularDependencyException|NotFoundException|ContainerException|\ReflectionException
     */
    protected function getEntryInstanceByIdClass(string $id, $entry)
    {
        if ($this->isServiceClassConfigPair($id, $entry)) {
            $parameters = isset($entry['parameters']) ? $entry['parameters'] : [];
            $preferences = isset($entry['preferences']) ? $entry['preferences'] : [];

            /* create class instance with parameters */
            $instance = $this->runMethod($id, '__construct', $preferences, $parameters);

            /* save instance to services if singleton is true */
            $singleton = isset($entry['singleton']) ? $entry['singleton'] : false;
            if (isset($this->services[$id]) && $singleton === true) $this->services[$id]['instance'] = $instance;

            return $instance;
        }

        return null;
    }

    /**
     * Get an entry instance of the application by it id interface.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return null|object
     * @throws CircularDependencyException|NotFoundException|ContainerException|\ReflectionException
     */
    protected function getEntryInstanceByIdInterface(string $id, $entry)
    {
        if ($this->isServiceInterfaceConfigPair($id, $entry)) {
            $class = $entry['class'];
            unset($entry['class']);
            $singleton = isset($entry['singleton']) ? $entry['singleton'] : false;
            unset($entry['singleton']);
            $entry = count($entry) ? $entry : null;

            /* create class instance with parameters */
            $instance = $this->get($class, $entry);

            /* save instance to services if singleton is true */
            if (isset($this->services[$id]) && $singleton === true) $this->services[$id]['instance'] = $instance;

            return $instance;
        }

        return null;
    }

    /**
     * Get an entry instance of the application by it id alias.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return null|mixed
     * @throws CircularDependencyException|NotFoundException|ContainerException|\ReflectionException
     */
    protected function getEntryInstanceByIdAlias(string $id, $entry)
    {
        if ($this->isServiceAliasConfigPair($id, $entry)) {
            $id = $entry['typeOf'];
            unset($entry['typeOf']);
            $entry = count($entry) ? $entry : null;

            return $this->get($id, $entry);
        }

        return null;
    }

    /**
     * Fire an event and run all associated tasks.
     *
     * @param string $event Name of the event
     * @param  array $data Data passed to the task
     *
     * @return array
     * @throws CircularDependencyException
     */
    public function fire(string $event, array $data = [])
    {
        /* return if event name is not valid */
        if (!$this->isNameValid($event)) return $data;

        /* run tasks on event */
        foreach ($this->getTasks(['event' => $event], 'and', 'index') as $task) {
            $data = $this->runTask($task, $data);
        }

        return $data;
    }

    /**
     * Run task with related tasks.
     *
     * @param string $task Task to run
     * @param  array $data Data passed to the task
     *
     * @return array
     * @throws CircularDependencyException
     */
    public function runTask(string $task, array $data = [])
    {
        /* add stack entry */
        $this->isEntryCircularDependent('task', $task);

        /* replace task */
        /* @var $task Task::class */
        if (count($replacementTasks = $this->getTasks(['event' => 'replace-task-' . $task::getName()], 'and', 'index'))) {
            /* remove stack entry */
            array_pop($this->dependencyStack['task']);

            return $this->runTask(reset($replacementTasks), $data);
        }

        /* run tasks before task */
        $data = $this->fire('before-task-' . $task::getName(), $data);

        /* run task */
        /* @var $taskInstance Task */
        $taskInstance = new $task();
        $returnData = $taskInstance->run($this, $data);
        $data = is_array($returnData) ? $returnData : $data;

        /* run tasks after task */
        $data = $this->fire('after-task-' . $task::getName(), $data);

        /* remove stack entry */
        array_pop($this->dependencyStack['task']);

        return $data;
    }

    /**
     * Run class method with preferences and parameters and return output.
     *
     * @param string $id Id of an entry
     * @param string $method Method to run
     * @param  array $preferences Array that maps class or interface names to a service name
     * @param  array $parameters Array of parameters to pass to method
     *
     * @return null|mixed
     * @throws CircularDependencyException|NotFoundException|ContainerException|\ReflectionException
     */
    public function runMethod(string $id, string $method = '__construct', array $preferences = [], array $parameters = [])
    {
        /* if class does not exists return null */
        if (!class_exists($id)) return null;

        /* if reflection class of id is not registered register it */
        if (!isset($this->reflectionClasses[$id])) $this->reflectionClasses[$id] = new \ReflectionClass($id);

        /* check if method is constructor */
        $isMethodConstructor = strcmp($method, '__construct') === 0;

        /* if method is constructor and constructor does not exist return instance without parameters */
        if ($isMethodConstructor && is_null($this->reflectionClasses[$id]->getConstructor())) return new $id();

        /* resolve parameters */
        $classParameters = $this->reflectionClasses[$id]->getMethod($method)->getParameters();
        foreach ($classParameters as $classParameter) {
            $classParameterName = $classParameter->getName();
            if (isset($parameters[$classParameterName])) continue;
            $classParameterType = $classParameter->getType()->getName();
            $preference = isset($preferences[$classParameterType]) ? $preferences[$classParameterType] : $classParameterType;
            $parameters[$classParameterName] = $this->get($preference);
        }

        /* arrange parameters in sequence method accepts */
        $classParametersNames = array_map(function (\ReflectionParameter $parameter) { return $parameter->getName(); }, $classParameters);
        $parameters = array_values(array_merge(array_flip($classParametersNames), $parameters));

        /* return output */
        if ($isMethodConstructor) {
            return new $id(...$parameters);
        }

        return $this->get($id)->$method(...$parameters);
    }

    /**
     * Get reflection classes registered to the application.
     *
     * @return \ReflectionClass[]
     */
    public function getReflectionClasses()
    {
        return $this->reflectionClasses;
    }

    /**
     * Get all the modules registered to the application.
     *
     * @return Module[]
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Get all the tasks registered to the application.
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
        $tasks = $this->tasks;

        /* filter tasks */
        if (count($filterBy)) {
            $tasks = array_filter($tasks, function (string $task) use ($filterBy, $filterLogic) {
                /* @var $task Task::class */
                $areNamesEqual = isset($filterBy['name']) ? $task::getName() === $filterBy['name'] : $filterLogic === 'and';
                $areEventsEqual = isset($filterBy['event']) ? $task::getEvent() === $filterBy['event'] : $filterLogic === 'and';

                return $filterLogic === 'and' ? $areNamesEqual && $areEventsEqual : $areNamesEqual || $areEventsEqual;
            });
        }

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
     * Get all the services registered to the application.
     *
     * @return array
     */
    public function getServices()
    {
        return $this->services;
    }

    /**
     * Get stack of instances of the application.
     *
     * @return array
     */
    public function getDependencyStack()
    {
        return $this->dependencyStack;
    }

    /**
     * Flush all the modules, tasks, services and instance stack of the application.
     */
    public function flush()
    {
        $this->modules = [];
        $this->tasks = [];
        $this->services = [];
        $this->dependencyStack = [];
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
     * Get local modules path of the application.
     *
     * @return string
     */
    public function getLocalPath()
    {
        return $this->getRootPath() . 'local' . DIRECTORY_SEPARATOR;
    }

    /**
     * Get vendor modules path of the application.
     *
     * @return string
     */
    public function getVendorPath()
    {
        return $this->getRootPath() . 'vendor' . DIRECTORY_SEPARATOR;
    }

    /**
     * Check whether name is valid module and task name.
     *
     * @param string $name Module or task name
     *
     * @return bool
     */
    public function isNameValid(string $name)
    {
        if (!empty($name) && preg_match('/^[0-9a-z-]{3,255}$/', $name)) {
            return true;
        }

        return false;
    }

    /**
     * Check if service is key-value pair.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return bool
     */
    public function isServiceKeyValuePair(string $id, $entry)
    {
        if (!class_exists($id) && !interface_exists($id)
            && (!is_array($entry) || (!key_exists('typeOf', $entry) && !key_exists('instance', $entry)))) {
            return true;
        }

        return false;
    }

    /**
     * Check if service is key-config pair.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return bool
     */
    public function isServiceKeyConfigPair(string $id, $entry)
    {
        if (is_array($entry) && key_exists('instance', $entry)) {
            return true;
        }

        return false;
    }

    /**
     * Check if service is class-config pair.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return bool
     */
    public function isServiceClassConfigPair(string $id, $entry)
    {
        if (class_exists($id) && is_array($entry)) {
            return true;
        }

        return false;
    }

    /**
     * Check if service is interface-class pair.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return bool
     */
    public function isServiceInterfaceClassPair(string $id, $entry)
    {
        if (interface_exists($id) && is_string($entry) && class_exists($entry)) {
            return true;
        }

        return false;
    }

    /**
     * Check if service is interface-config pair.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return bool
     */
    public function isServiceInterfaceConfigPair(string $id, $entry)
    {
        if (interface_exists($id) && is_array($entry) && key_exists('class', $entry) && class_exists($entry['class'])) {
            return true;
        }

        return false;
    }

    /**
     * Check if service is alias-config pair.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return bool
     */
    public function isServiceAliasConfigPair(string $id, $entry)
    {
        if (!class_exists($id) && !interface_exists($id) && is_array($entry) && key_exists('typeOf', $entry)) {
            return true;
        }

        return false;
    }

    /**
     * Check if there is circular dependency.
     *
     * @param string $stackId Unique stack id
     * @param string $entry Entry to check
     *
     * @throws CircularDependencyException
     */
    public function isEntryCircularDependent(string $stackId, string $entry)
    {
        if (!isset($this->dependencyStack[$stackId])) $this->dependencyStack[$stackId] = [];

        if (in_array($entry, $this->dependencyStack[$stackId])) {
            throw new CircularDependencyException(sprintf(
                'Circular dependency of stack "%s": %s -> %s',
                $stackId,
                implode(' -> ', $this->dependencyStack[$stackId]),
                $entry
            ));
        } else {
            $this->dependencyStack[$stackId][] = $entry;
        }
    }

    /**
     * Convert string with delimiters to camelcase.
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
