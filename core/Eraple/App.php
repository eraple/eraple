<?php

namespace Eraple;

/**
 * Class App
 */
class App
{
    /* @var string Application version. */
    protected $version = '1.0.0';

    /* @var string Application root path. */
    protected $rootPath = '';

    /* @var App Application instance. */
    protected static $instance;

    /* @var array Registered modules. */
    protected $modules = [];

    /* @var array Registered tasks. */
    protected $tasks = [];

    /* @var array Registered resources. */
    protected $resources = [];

    /**
     * Application constructor.
     *
     * @param string $rootPath
     */
    public function __construct(string $rootPath)
    {
        $this->rootPath = $rootPath;
        self::$instance = $this;
    }

    /**
     * Get application global instance.
     *
     * @param string $rootPath
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
        $localModules = glob($this->localPath() . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*');
        $vendorModules = glob($this->vendorPath() . DIRECTORY_SEPARATOR . '*' . DIRECTORY_SEPARATOR . '*');

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
     * Bind module with the application.
     *
     * @param string $name Name of the module
     * @param string $class Module class
     */
    public function registerModule(string $name, string $class)
    {
        $this->modules[$name] = ['name' => $name, 'class' => $class];
    }

    /**
     * Bind task with the application.
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
        /* check whether its a before or after event */
        $isBeforeAfterEvent = preg_match('/^before_/', $event) || preg_match('/^after_/', $event);

        /* run before event of an event */
        if (!$isBeforeAfterEvent) $data = $this->fireEvent('before_' . $event, $data);

        /* find tasks tasks to run on event */
        $afterTasks = array_filter($this->tasks, function ($afterTask) use ($event) { return $afterTask['position'] === 'event_' . $event; });
        usort($afterTasks, function ($task1, $task2) { return $task1['priority'] < $task2['priority']; });

        /* run tasks to run after event */
        foreach ($afterTasks as $afterTask) {
            $data = array_merge($data, $this->runTaskSequence($afterTask, $data));
        }

        /* run after event of an event */
        if (!$isBeforeAfterEvent) $data = $this->fireEvent('after_' . $event, $data);

        return $data;
    }

    /**
     * Run all the tasks in sequence.
     *
     * @param array $task Task
     * @param array $data Data
     *
     * @return array
     */
    protected function runTaskSequence(array $task, array $data = [])
    {
        /* find tasks to run before task */
        $beforeTasks = array_filter($this->tasks, function ($beforeTask) use ($task) { return $beforeTask['position'] === 'before_' . $task['name']; });
        usort($beforeTasks, function ($task1, $task2) { return $task1['priority'] < $task2['priority']; });

        /* run tasks to run before task */
        foreach ($beforeTasks as $beforeTask) {
            $data = array_merge($data, $this->runTaskSequence($beforeTask, $data));
        }

        /* run task */
        /* @var $taskClass Task */
        $taskClass = $task['class'];
        $taskClass = new $taskClass();
        $returnData = $taskClass->run($this, $data);
        $data = array_merge($data, !is_array($returnData) ? [] : $returnData);

        /* find tasks to run after task */
        $afterTasks = array_filter($this->tasks, function ($afterTask) use ($task) { return $afterTask['position'] === 'after_' . $task['name']; });
        usort($afterTasks, function ($task1, $task2) { return $task1['priority'] < $task2['priority']; });

        /* run tasks to run after task */
        foreach ($afterTasks as $afterTask) {
            $data = array_merge($data, $this->runTaskSequence($afterTask, $data));
        }

        return $data;
    }

    /**
     * Get root path of the application.
     *
     * @return string
     */
    public function rootPath()
    {
        return trim($this->rootPath, '/\\');
    }

    /**
     * Get local modules path of the application.
     *
     * @return string
     */
    public function localPath()
    {
        return $this->rootPath() . DIRECTORY_SEPARATOR . 'local';
    }

    /**
     * Get vendor modules path of the application.
     *
     * @return string
     */
    public function vendorPath()
    {
        return $this->rootPath() . DIRECTORY_SEPARATOR . 'vendor';
    }

    /**
     * Check whether module is an Eraple module.
     *
     * @param string $path
     * @return bool
     */
    public function isErapleModule(string $path)
    {
        return file_exists($path . DIRECTORY_SEPARATOR . 'Module.php');
    }
}
