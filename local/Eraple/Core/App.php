<?php

namespace Eraple\Core;

use Psr\Container\ContainerInterface;
use Eraple\Core\Exception\CircularDependencyException;
use Eraple\Core\Exception\NotFoundException;
use Eraple\Core\Exception\MissingParameterException;
use Eraple\Core\Exception\ContainerException;

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
     * Set reflection classes.
     *
     * @var \ReflectionClass[]|\ReflectionFunction[]
     */
    protected $reflectionClasses = [];

    /**
     * Set modules.
     *
     * @var Module[]
     */
    protected $modules = [];

    /**
     * Set tasks.
     *
     * @var Task[]
     */
    protected $tasks = [];

    /**
     * Set services.
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
        $this->set(App::class, $this);
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
        $this->setModules();
        $this->setTasks();
        $this->setServices();
        $this->fire('before-start');
        $this->fire('start');
        $this->fire('after-start');
        $this->fire('before-end');
        $this->fire('end');
        $this->fire('after-end');
    }

    /**
     * Set modules in local and vendor paths.
     */
    protected function setModules()
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
     * Set module tasks.
     */
    protected function setTasks()
    {
        /* set tasks */
        foreach ($this->modules as $module) {
            $tasks = $module::getTasks();

            foreach ($tasks as $task) {
                $this->setTask($task);
            }
        }
    }

    /**
     * Set services.
     */
    protected function setServices()
    {
        /* set services */
        foreach ($this->tasks as $task) {
            $services = $task::getServices();

            foreach ($services as $serviceId => $service) {
                $this->setService($serviceId, $service);
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
     * Prepare service before setting it to the application.
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

        /* discard service with invalid name */
        if (!class_exists($id) && !interface_exists($id) && !$this->isNameValid($id)) return null;

        /* process entry with id as key and entry as value */
        if ($this->isServiceKeyValuePair($id, $entry)) return ['instance' => $entry];

        /* process entry with id as class and entry as instance */
        if ($this->isServiceClassInstancePair($id, $entry)) return ['instance' => $entry];

        /* process entry with id as interface and entry as class */
        if ($this->isServiceInterfaceClassPair($id, $entry)) return ['class' => $entry];

        /* process entry with id as interface and entry as instance */
        if ($this->isServiceInterfaceInstancePair($id, $entry)) return ['instance' => $entry];

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
        if (key_exists($id, $this->services)) return true;

        return class_exists($id) && !interface_exists($id);
    }

    /**
     * Get an instance of the application service by its id.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return mixed
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function get($id, $entry = null)
    {
        /* add stack entry */
        $this->isEntryCircularDependent('instance', $id);

        /* prepare service entry */
        $entry = $this->prepareServiceEntry($id, $entry);

        /* entry not found and not instantiable */
        if (!$this->has($id) && is_null($entry)) throw new NotFoundException(sprintf('Id "%s" not found', $id));

        /* entry not found but instantiable */
        if (!key_exists($id, $this->services) && is_null($entry)) {
            $instance = $this->runMethod($id);
            /* remove stack entry */
            array_pop($this->dependencyStack['instance']);

            return $instance;
        }

        /* entry found and instantiable */
        $functions = ['getEntryInstanceByIdKey', 'getEntryInstanceByIdClass', 'getEntryInstanceByIdInterface', 'getEntryInstanceByIdAlias'];
        $entry = !is_null($entry) ? $entry : $this->services[$id];
        $entry = is_array($entry) && key_exists($id, $this->services) && is_array($this->services[$id]) ? array_merge($this->services[$id], $entry) : $entry;
        foreach ($functions as $function) {
            $instance = $this->$function($id, $entry);
            if (!is_null($instance)) {
                /* remove stack entry */
                array_pop($this->dependencyStack['instance']);

                return $instance;
            }
        }

        /* throw exception if entry not instantiable */
        throw new ContainerException(sprintf('Entry of id "%s" is invalid', $id));
    }

    /**
     * Get an entry instance of the application by its id key.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return null|mixed
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws ContainerException
     * @throws \ReflectionException
     */
    protected function getEntryInstanceByIdKey(string $id, $entry)
    {
        if ($this->isServiceKeyConfigPair($id, $entry)) {
            if ($entry['instance'] instanceof \Closure) {
                $services = key_exists('services', $entry) ? $entry['services'] : [];
                $parameters = key_exists('parameters', $entry) ? $entry['parameters'] : [];

                return $this->runMethod($id, $entry['instance'], $services, $parameters);
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
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws ContainerException
     * @throws \ReflectionException
     */
    protected function getEntryInstanceByIdClass(string $id, $entry)
    {
        if ($this->isServiceClassConfigPair($id, $entry)) {
            $services = key_exists('services', $entry) ? $entry['services'] : [];
            $parameters = key_exists('parameters', $entry) ? $entry['parameters'] : [];

            /* create class instance with parameters */
            $instance = $this->runMethod($id, '__construct', $services, $parameters);

            /* save instance to services if singleton is true */
            $singleton = key_exists('singleton', $entry) ? $entry['singleton'] : false;
            if (key_exists($id, $this->services) && $singleton === true) $this->services[$id]['instance'] = $instance;

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
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws ContainerException
     * @throws \ReflectionException
     */
    protected function getEntryInstanceByIdInterface(string $id, $entry)
    {
        if ($this->isServiceInterfaceConfigPair($id, $entry)) {
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

        return null;
    }

    /**
     * Get an entry instance of the application by it id alias.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return null|mixed
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws ContainerException
     * @throws \ReflectionException
     */
    protected function getEntryInstanceByIdAlias(string $id, $entry)
    {
        if ($this->isServiceAliasConfigPair($id, $entry)) {
            $id = $entry['alias'];
            unset($entry['alias']);
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
     * @return array|null|mixed
     * @throws CircularDependencyException
     * @throws ContainerException
     * @throws MissingParameterException
     * @throws NotFoundException
     * @throws \ReflectionException
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
     * @return array|null|mixed
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws ContainerException
     * @throws \ReflectionException
     */
    public function runTask(string $task, array $data = [])
    {
        /* add stack entry */
        $this->isEntryCircularDependent('task', $task);

        /* replace task */
        /* @var $task Task::class */
        if (count($replacementTasks = $this->getTasks(['event' => 'replace-task-' . $task::getName()], 'and', 'index'))) {
            $data = $this->runTask(reset($replacementTasks), $data);
            /* remove stack entry */
            array_pop($this->dependencyStack['task']);

            return $data;
        }

        /* run tasks before task */
        $data = $this->fire('before-task-' . $task::getName(), $data);

        /* run task */
        $returnData = $this->runMethod($task, 'run', [], ['data' => $data]);
        $data = is_array($returnData) ? $returnData : $data;

        /* run tasks after task */
        $data = $this->fire('after-task-' . $task::getName(), $data);

        /* remove stack entry */
        array_pop($this->dependencyStack['task']);

        return $data;
    }

    /**
     * Run class method with services and parameters and return output.
     *
     * @param string $id Id of an entry
     * @param  mixed $method Class method or closure to run
     * @param  array $services Array that maps class or interface names to a service name
     * @param  array $parameters Array of parameters to pass to method
     *
     * @return null|mixed
     * @throws CircularDependencyException
     * @throws NotFoundException
     * @throws MissingParameterException
     * @throws ContainerException
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
        if (is_string($method) && !key_exists($id, $this->reflectionClasses)) $this->reflectionClasses[$id] = new \ReflectionClass($id);

        /* if method is closure and reflection not set set it */
        if (!is_string($method) && !key_exists($id, $this->reflectionClasses)) $this->reflectionClasses[$id] = new \ReflectionFunction($method);

        /* if method is constructor and constructor does not exist return instance without parameters */
        if ($isMethodConstructor && is_null($this->reflectionClasses[$id]->getConstructor())) return new $id();

        /* get reflection method */
        $reflectionMethod = is_string($method) ? $this->reflectionClasses[$id]->getMethod($method) : $this->reflectionClasses[$id];

        /* resolve parameters */
        $classParameters = $reflectionMethod->getParameters();
        foreach ($classParameters as $classParameter) {
            $classParameterName = $classParameter->getName();
            if (key_exists($classParameterName, $parameters)) continue;
            $classParameterType = $classParameter->getType();
            if (is_null($classParameterType)) throw new MissingParameterException(sprintf(
                'Parameter "%s" of method %s in id %s is missing', $classParameterName, is_string($method) ? $method : 'closure', $id
            ));
            $classParameterType = $classParameter->getType()->getName();
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
     * Get reflection classes set to the application.
     *
     * @return \ReflectionClass[]
     */
    public function getReflectionClasses()
    {
        return $this->reflectionClasses;
    }

    /**
     * Get all the modules set to the application.
     *
     * @return Module[]
     */
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Get all the tasks set to the application.
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
                $areNamesEqual = key_exists('name', $filterBy) ? $task::getName() === $filterBy['name'] : $filterLogic === 'and';
                $areEventsEqual = key_exists('event', $filterBy) ? $task::getEvent() === $filterBy['event'] : $filterLogic === 'and';

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
     * Get all the services set to the application.
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
            && (!is_array($entry) || (!key_exists('alias', $entry) && !key_exists('instance', $entry)))) {
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
     * Check if service is class-instance pair.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return bool
     */
    public function isServiceClassInstancePair(string $id, $entry)
    {
        if (class_exists($id) && is_object($entry) && $entry instanceof $id) {
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
     * Check if service is interface-instance pair.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return bool
     */
    public function isServiceInterfaceInstancePair(string $id, $entry)
    {
        if (interface_exists($id) && is_object($entry) && $entry instanceof $id) {
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
        if (!class_exists($id) && !interface_exists($id) && is_array($entry) && key_exists('alias', $entry)) {
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
        if (!key_exists($stackId, $this->dependencyStack)) $this->dependencyStack[$stackId] = [];

        if (in_array($entry, $this->dependencyStack[$stackId])) {
            throw new CircularDependencyException(
                sprintf('Circular dependency in stack "%s": %s -> %s', $stackId, implode(' -> ', $this->dependencyStack[$stackId]), $entry)
            );
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
