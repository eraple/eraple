<?php

namespace Eraple;

use Psr\Container\ContainerInterface;
use Zend\Di\Injector;
use Zend\Di\Definition\RuntimeDefinition;
use Eraple\Exception\NotFoundException;
use Eraple\Exception\CircularDependencyException;
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
     * Dependency injector.
     *
     * @var Injector
     */
    protected $injector;

    /**
     * Class definitions based on runtime reflection.
     *
     * @var RuntimeDefinition
     */
    protected $definition;

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
        $this->injector = new Injector(null, $this);
        $this->definition = new RuntimeDefinition();
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
                $this->registerModule($module);
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
            /* @var $moduleInstance Module */
            $moduleInstance = new $module();
            $moduleInstance->registerTasks($this);
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
     * Register module with the application.
     *
     * @param string $class Module class
     */
    public function registerModule(string $class)
    {
        /* @var $class Module::class */
        if (!is_subclass_of($class, Module::class) || !$this->isNameValid($class::getName())) return;

        $this->modules[$class::getName()] = $class;
    }

    /**
     * Register task with the application.
     *
     * @param string $class Task class
     */
    public function registerTask(string $class)
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
    public function registerService(string $id, $entry)
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
        /* check id is not class and not interface */
        $isIdNotClassAndNotInterface = !class_exists($id) && !interface_exists($id);

        /* discard entry with invalid name */
        if ($isIdNotClassAndNotInterface && !$this->isNameValid($id)) return $this;

        /* check entry is not alias and not instance */
        $isEntryNotAliasAndNotInstance = !is_array($entry) || (!isset($entry['typeOf']) && !isset($entry['instance']));

        /* process entry with id as key and entry as value */
        if ($isIdNotClassAndNotInterface && $isEntryNotAliasAndNotInstance) $entry = ['instance' => $entry];

        /* check id is interface and entry is class */
        $isIdInterfaceAndEntryClass = interface_exists($id) && is_string($entry) && class_exists($entry);

        /* process entry with id as interface and entry as class */
        if ($isIdInterfaceAndEntryClass) $entry = ['concrete' => $entry];

        /* set service to the application */
        $this->services[$id] = $entry;

        return $this;
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

        return $this->injector->canCreate($id);
    }

    /**
     * Get an instance of the application service by its id.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return mixed
     * @throws NotFoundException|ContainerException
     */
    public function get($id, $entry = null)
    {
        /* add stack entry */
        $this->isEntryCircularDependent('instance', $id);

        /* entry not found and not instantiable */
        if (!$this->has($id)) throw new NotFoundException();

        /* entry not found but instantiable */
        if (!isset($this->services[$id])) {
            /* remove stack entry */
            array_pop($this->dependencyStack['instance']);

            return $this->injector->create($id);
        }

        /* entry found and instantiable */
        $functions = ['getEntryInstanceByIdKey', 'getEntryInstanceByIdClass', 'getEntryInstanceByIdInterface', 'getEntryInstanceByIdAlias'];
        $entry = !is_null($entry) ? $entry : $this->services[$id];
        $entry = is_array($entry) && is_array($this->services[$id]) ? array_merge($this->services[$id], $entry) : $entry;
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
        if (is_array($entry) && isset($entry['instance'])) {
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
     * @throws NotFoundException|ContainerException
     */
    protected function getEntryInstanceByIdClass(string $id, $entry)
    {
        if (class_exists($id) && is_array($entry)) {
            $parameters = isset($entry['parameters']) ? $entry['parameters'] : [];
            $preferences = isset($entry['preferences']) ? $entry['preferences'] : [];

            /* add preferences as parameters */
            $classParameters = $this->definition->getClassDefinition($id)->getParameters();
            foreach ($classParameters as $classParameter) {
                $classParameterName = $classParameter->getName();
                if (isset($parameters[$classParameterName])) continue;
                $classParameterType = $classParameter->getType();
                if (isset($preferences[$classParameterType])) $parameters[$classParameterName] = $this->get($preferences[$classParameterType]);
            }

            /* create class instance with parameters */
            $instance = $this->injector->create($id, $parameters);

            /* save instance to services if singleton is true */
            if (isset($entry['singleton']) && $entry['singleton'] === true) $this->services[$id]['instance'] = $instance;

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
     * @throws NotFoundException|ContainerException
     */
    protected function getEntryInstanceByIdInterface(string $id, $entry)
    {
        if (interface_exists($id) && is_array($entry) && isset($entry['concrete']) && class_exists($entry['concrete'])) {
            $concrete = $entry['concrete'];
            unset($entry['concrete']);
            $entry = count($entry) ? $entry : null;

            return $this->get($concrete, $entry);
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
     * @throws NotFoundException|ContainerException
     */
    protected function getEntryInstanceByIdAlias(string $id, $entry)
    {
        if (!class_exists($id) && !interface_exists($id) && is_array($entry) && isset($entry['typeOf'])) {
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
     * Get injector instance of the application.
     *
     * @return Injector
     */
    public function getInjector()
    {
        return $this->injector;
    }

    /**
     * Get runtime definition instance of the application.
     *
     * @return RuntimeDefinition
     */
    public function getDefinition()
    {
        return $this->definition;
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
