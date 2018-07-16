<?php

namespace Eraple;

use Psr\Container\ContainerInterface;
use Zend\Di\Injector;
use Zend\Di\Definition\RuntimeDefinition;
use Zend\Di\Exception\CircularDependencyException;

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
     * Instance stack.
     *
     * @var array
     */
    protected $instanceStack = [];

    /**
     * Application constructor.
     *
     * @param string $rootPath Root path of the application
     */
    public function __construct(string $rootPath = null)
    {
        $this->rootPath = $rootPath;
        self::$instance = $this;
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
        $this->fireEvent('start');
        $this->fireEvent('end');
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
            /* @var $moduleClass Module */
            $moduleClass = $module;
            $moduleClass = new $moduleClass();
            $moduleClass->registerTasks($this);
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
        /* @var $class Module */
        if (!$this->isValidName($class::getName())) return;

        $this->modules[$class::getName()] = $class;
    }

    /**
     * Register task with the application.
     *
     * @param string $class Task class
     */
    public function registerTask(string $class)
    {
        /* @var $class Task */
        if (!$this->isValidName($class::getName())) return;

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
    public function fireEvent(string $event, array $data = [])
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
    protected function runTasksByPosition(string $position, array $data = [])
    {
        foreach ($this->getTasksByPosition($position) as $task) {
            $data = array_merge($data, $this->runTask($task, $data));
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
    protected function getTasksByPosition(string $position)
    {
        $tasks = array_filter($this->tasks, function (string $task) use ($position) {
            /* @var $task Task */
            return $task::getPosition() === $position;
        });
        usort($tasks, function (string $task1, string $task2) {
            /* @var $task1 Task */
            /* @var $task2 Task */
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
    protected function runTask(string $task, array $data = [])
    {
        /* replace task chain */
        /* @var $task Task */
        $replaceChainTasks = $this->getTasksByPosition('replace_chain_' . $task::getName());
        $task = count($replaceChainTasks) ? reset($replaceChainTasks) : $task;

        /* replace task */
        $replaceTasks = $this->getTasksByPosition('replace_' . $task::getName());
        $replacedTask = count($replaceTasks) ? reset($replaceTasks) : $task;

        /* @var $taskClass Task */
        $taskClass = $replacedTask;

        /* run tasks before task */
        $data = $this->runTasksByPosition('before_' . $task::getName(), $data);

        /* run tasks before replaced task */
        $data = strcmp($replacedTask::getName(), $task::getName()) !== 0 ? $this->runTasksByPosition('before_' . $replacedTask::getName(), $data) : $data;

        /* run task */
        $taskClass = new $taskClass();
        $returnData = $taskClass->run($this, $data);
        $data = array_merge($data, !is_array($returnData) ? [] : $returnData);

        /* run tasks after replaced task */
        $data = strcmp($replacedTask::getName(), $task::getName()) !== 0 ? $this->runTasksByPosition('after_' . $replacedTask::getName(), $data) : $data;

        /* run tasks after task */
        $data = $this->runTasksByPosition('after_' . $task::getName(), $data);

        return $data;
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
        /* entry not found and not instantiable */
        if (!$this->has($id)) {
            throw new NotFoundException();
        }

        /* entry not found but instantiable */
        if (!isset($this->resources[$id])) {
            return $this->injector->create($id);
        }

        /* check circular dependency */
        if (in_array($id, $this->instanceStack)) {
            throw new CircularDependencyException(sprintf(
                'Circular dependency: %s -> %s',
                implode(' -> ', $this->instanceStack),
                $id
            ));
        } else {
            $this->instanceStack[] = $id;
        }

        /* entry found and instantiable */
        $functions = [
            'getEntryInstanceByIdKey',
            'getEntryInstanceByIdClass',
            'getEntryInstanceByIdInterface',
            'getEntryInstanceByIdAlias'
        ];
        $entry = ($entry !== null) ? $entry : $this->resources[$id];
        $entry = (is_array($entry) && is_array($this->resources[$id])) ? array_merge($this->resources[$id], $entry) : $entry;
        foreach ($functions as $function) {
            $instance = $this->$function($id, $entry);
            if ($instance !== null) {
                array_pop($this->instanceStack);

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
            $parameters = (isset($entry['parameters'])) ? $entry['parameters'] : [];
            $preferences = (isset($entry['preferences'])) ? $entry['preferences'] : [];

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
    public function getInstanceStack()
    {
        return $this->instanceStack;
    }

    /**
     * Flush all the modules, tasks, resources and instance stack of the application.
     */
    public function flush()
    {
        $this->modules = [];
        $this->tasks = [];
        $this->resources = [];
        $this->instanceStack = [];
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
