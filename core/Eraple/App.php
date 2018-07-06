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
     * @param string $name Name of the module
     * @param string $class Module class
     */
    public function registerModule(string $name, string $class)
    {
        $this->modules[$name] = ['name' => $name, 'class' => $class];
    }

    /**
     * Register task with the application.
     *
     * @param string $name Name of the task
     * @param string $class Task class
     */
    public function registerTask(string $name, string $class)
    {
        $position = !empty($class::$position) ? $class::$position : 'event_before_end';
        $priority = !empty($class::$priority) ? $class::$priority : 0;

        $this->tasks[$name] = ['name' => $name, 'class' => $class, 'position' => $position, 'priority' => $priority];
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
        /* replace task */
        $replaceTasks = $this->getTasksByPosition('replace_' . $task['name']);
        $task = count($replaceTasks) ? reset($replaceTasks) : $task;

        /* run tasks before task */
        $data = $this->runTasksByPosition('before_' . $task['name'], $data);

        /* run task */
        /* @var $taskClass Task */
        $taskClass = $task['class'];
        $taskClass = new $taskClass();
        $returnData = $taskClass->run($this, $data);
        $data = array_merge($data, !is_array($returnData) ? [] : $returnData);

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
