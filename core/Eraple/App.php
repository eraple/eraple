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
    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        self::$instance = $this;
    }

    /**
     * Get application global instance.
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
    public function version()
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
            if ($this->isErapleModule($module)) {
                require_once $module . DIRECTORY_SEPARATOR . 'Module.php';
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
            $moduleClass = $module['class'];
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
        $name = $class::getName();
        if ($this->isValidName($name)) {
            $this->modules[$name] = ['name' => $name, 'class' => $class];
        }
    }

    /**
     * Register task with the application.
     *
     * @param string $class Task class
     */
    public function registerTask(string $class)
    {
        /* @var $class Task */
        $name = $class::getName();
        if ($this->isValidName($name)) {
            $this->tasks[$name] = ['name' => $name, 'class' => $class, 'position' => $class::getPosition(), 'priority' => $class::getPriority()];
        }
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
     * @param array $data Data passed to the task
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
        $tasks = array_filter($this->tasks, function ($task) use ($position) { return $task['position'] === $position; });
        usort($tasks, function ($task1, $task2) { return $task1['priority'] < $task2['priority']; });

        return $tasks;
    }

    /**
     * Run task with related tasks.
     *
     * @param array $task Task to run
     * @param array $data Data passed to the task
     *
     * @return array
     */
    protected function runTask(array $task, array $data = [])
    {
        /* replace task chain */
        $replaceChainTasks = $this->getTasksByPosition('replace_chain_' . $task['name']);
        $task = count($replaceChainTasks) ? reset($replaceChainTasks) : $task;

        /* replace task */
        $replaceTasks = $this->getTasksByPosition('replace_' . $task['name']);
        $replacedTask = count($replaceTasks) ? reset($replaceTasks) : $task;
        /* @var $taskClass Task */
        $taskClass = $replacedTask['class'];

        /* run tasks before task */
        $data = $this->runTasksByPosition('before_' . $task['name'], $data);

        /* run tasks before replaced task */
        $data = strcmp($replacedTask['name'], $task['name']) !== 0 ? $this->runTasksByPosition('before_' . $replacedTask['name'], $data) : $data;

        /* run task */
        $taskClass = new $taskClass();
        $returnData = $taskClass->run($this, $data);
        $data = array_merge($data, !is_array($returnData) ? [] : $returnData);

        /* run tasks after replaced task */
        $data = strcmp($replacedTask['name'], $task['name']) !== 0 ? $this->runTasksByPosition('after_' . $replacedTask['name'], $data) : $data;

        /* run tasks after task */
        $data = $this->runTasksByPosition('after_' . $task['name'], $data);

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

    public function isValidName(string $name)
    {
        if (!empty($name) && preg_match('/^[0-9a-z-]{3,255}$/', $name)) {
            return true;
        }

        return false;
    }

    /**
     * Check whether module is an Eraple module.
     *
     * @param string $path Module path
     * @return bool
     */
    public function isErapleModule(string $path)
    {
        return file_exists(trim($path, '/\\') . DIRECTORY_SEPARATOR . 'Module.php');
    }
}
