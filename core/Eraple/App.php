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
     * Registered resources.
     *
     * @var array
     */
    protected $resources = [];

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
        $this->registerResources();
        $this->fire('start');
        $this->fire('end');
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
     * Register resources.
     */
    protected function registerResources()
    {
        /* register resources */
        foreach ($this->tasks as $task) {
            $resources = $task::getResources();

            foreach ($resources as $resourceId => $resource) {
                $this->set($resourceId, $resource);
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
        if (!is_subclass_of($class, Module::class) || !$this->isValidName($class::getName())) return;

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
        if (!is_subclass_of($class, Task::class) || !$this->isValidName($class::getName())) return;

        $this->tasks[$class::getName()] = $class;
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
        if (!$this->isValidName($event)) return $data;

        /* run tasks before event */
        $data = $this->runTasksByPosition('event_before_' . $event, $data);

        /* run tasks on event */
        $data = $this->runTasksByPosition('event_' . $event, $data);

        /* run tasks after event */
        $data = $this->runTasksByPosition('event_after_' . $event, $data);

        return $data;
    }

    /**
     * Run tasks by position.
     *
     * @param string $position Position of the task
     * @param  array $data Data passed to the task
     *
     * @return array
     */
    public function runTasksByPosition(string $position, array $data = [])
    {
        foreach ($this->getTasksByPosition($position) as $task) {
            $data = $this->runTask($task, $data);
        }

        return $data;
    }

    /**
     * Get tasks by position.
     *
     * @param string $position Position of the task
     *
     * @return array
     */
    public function getTasksByPosition(string $position)
    {
        $tasks = array_filter($this->tasks, function (string $task) use ($position) {
            /* @var $task Task::class */
            return $task::getPosition() === $position;
        });
        usort($tasks, function (string $task1, string $task2) {
            /* @var $task1 Task::class */
            /* @var $task2 Task::class */
            return $task1::getPriority() < $task2::getPriority();
        });

        return $tasks;
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
        /* @var $task Task::class */

        /* add stack entry */
        $this->checkCircularDependency('task', $task);

        /* replace task chain */
        if (count($replaceChainTasks = $this->getTasksByPosition('replace_chain_' . $task::getName()))) {
            /* remove stack entry */
            array_pop($this->dependencyStack['task']);

            return $this->runTask(reset($replaceChainTasks), $data);
        }

        /* run tasks before task */
        $data = $this->runTasksByPosition('before_' . $task::getName(), $data);

        /* run replaced task or task */
        if (count($replaceTasks = $this->getTasksByPosition('replace_' . $task::getName()))) {
            $data = $this->runTask(reset($replaceTasks), $data);
        } else {
            /* @var $taskInstance Task */
            $taskInstance = new $task();
            $returnData = $taskInstance->run($this, $data);
            $data = is_array($returnData) ? $returnData : $data;
        }

        /* run tasks after task */
        $data = $this->runTasksByPosition('after_' . $task::getName(), $data);

        /* remove stack entry */
        array_pop($this->dependencyStack['task']);

        return $data;
    }

    /**
     * Check if there is circular dependency.
     *
     * @param string $stackId Unique stack id
     * @param   string $entry Entry to check
     * @throws CircularDependencyException
     */
    protected function checkCircularDependency(string $stackId, string $entry)
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
     * Check whether an entry exists in the application.
     *
     * @param string $id Id of an entry
     *
     * @return bool
     */
    public function has($id)
    {
        if (isset($this->resources[$id])) {
            return true;
        }

        return $this->injector->canCreate($id);
    }

    /**
     * Get an instance of the application resource by its id.
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
        $this->checkCircularDependency('instance', $id);

        /* entry not found and not instantiable */
        if (!$this->has($id)) {
            throw new NotFoundException();
        }

        /* entry not found but instantiable */
        if (!isset($this->resources[$id])) {
            /* remove stack entry */
            array_pop($this->dependencyStack['instance']);

            return $this->injector->create($id);
        }

        /* entry found and instantiable */
        $functions = [
            'getEntryInstanceByIdKey',
            'getEntryInstanceByIdClass',
            'getEntryInstanceByIdInterface',
            'getEntryInstanceByIdAlias'
        ];
        $entry = !is_null($entry) ? $entry : $this->resources[$id];
        $entry = is_array($entry) && is_array($this->resources[$id]) ? array_merge($this->resources[$id], $entry) : $entry;
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
                $classParameterType = $classParameter->getType();

                if (isset($parameters[$classParameterName])) {
                    continue;
                }

                if (isset($preferences[$classParameterType])) {
                    $parameters[$classParameterName] = $this->get($preferences[$classParameterType]);
                }
            }

            /* create class instance with parameters */
            $instance = $this->injector->create($id, $parameters);

            /* save instance to resources if singleton is true */
            if (isset($entry['singleton']) && $entry['singleton'] === true) {
                $this->resources[$id]['instance'] = $instance;
            }

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
     * Set an entry to the application.
     *
     * @param string $id Id of an entry
     * @param  mixed $entry Entry of the application
     *
     * @return $this
     */
    public function set(string $id, $entry)
    {
        /* discard entry with invalid name */
        if (!class_exists($id) && !interface_exists($id) && !$this->isValidName($id)) return $this;

        /* process entry with id as key and entry as value */
        if (!class_exists($id) && !interface_exists($id)
            && (!is_array($entry) || (!isset($entry['typeOf']) && !isset($entry['instance'])))) {
            $entry = ['instance' => $entry];
        }

        /* process entry with id as interface and entry as class */
        if (interface_exists($id) && is_string($entry) && class_exists($entry)) {
            $entry = ['concrete' => $entry];
        }

        $this->resources[$id] = $entry;

        return $this;
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
     * @return Task[]
     */
    public function getTasks()
    {
        return $this->tasks;
    }

    /**
     * Get all the resources registered to the application.
     *
     * @return array
     */
    public function getResources()
    {
        return $this->resources;
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
     * Flush all the modules, tasks, resources and instance stack of the application.
     */
    public function flush()
    {
        $this->modules = [];
        $this->tasks = [];
        $this->resources = [];
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
    public function isValidName(string $name)
    {
        if (!empty($name) && preg_match('/^[0-9a-z-]{3,255}$/', $name)) {
            return true;
        }

        return false;
    }

    /**
     * Convert string with delimiters to camelcase.
     *
     * @param string $string
     * @param string $delimiter
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
     * @param string $string
     * @param string $delimiter
     *
     * @return string
     */
    public function uncamelize(string $string, string $delimiter = '-')
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '\\1' . $delimiter . '\\2', $string));
    }

    /**
     * Access resource instance by name and arguments.
     *
     * @param string $name Name of the resource
     * @param  array $arguments
     *
     * @return mixed
     * @throws NotFoundException|ContainerException
     */
    public function __call(string $name, $arguments)
    {
        return $this->get($this->uncamelize($name));
    }
}
