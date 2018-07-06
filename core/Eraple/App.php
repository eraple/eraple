<?php

namespace Eraple;

class App
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
     * Registered modules.
     *
     * @var array
     */
    protected $modules = [];

    /**
     * Registered tasks.
     *
     * @var array
     */
    protected $tasks = [];

    /**
     * Registered resources.
     *
     * @var array
     */
    protected $resources = [];

    /**
     * Application constructor.
     *
     * @param string $rootPath Root path of the application
     */
    public function __construct(string $rootPath = null)
    {
        $this->rootPath = $rootPath;
        self::$instance = $this;
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
}
