<?php

namespace Eraple;

abstract class Task
{
    /**
     * Task name.
     *
     * @var string
     */
    protected static $name = '';

    /**
     * Task description.
     *
     * @var string
     */
    protected static $description = 'No description found.';

    /**
     * Task position.
     *
     * @var string
     */
    protected static $position = 'event_before_end';

    /**
     * Task priority.
     *
     * @var int
     */
    protected static $priority = 0;

    /**
     * Task resources.
     *
     * @var array
     */
    protected static $resources = [];

    /**
     * Get the task name.
     *
     * @return string
     */
    public static function getName()
    {
        return static::$name;
    }

    /**
     * Get the task description.
     *
     * @return string
     */
    public static function getDescription()
    {
        return static::$description;
    }

    /**
     * Get the task position.
     *
     * @return string
     */
    public static function getPosition()
    {
        return static::$position;
    }

    /**
     * Get the task priority.
     *
     * @return int
     */
    public static function getPriority()
    {
        return static::$priority;
    }

    /**
     * Get the task resources.
     */
    public static function getResources()
    {
        return static::$resources;
    }

    /**
     * Run the task.
     *
     * @param App $app Application instance
     * @param array $data Data to process
     *
     * @return mixed
     */
    abstract public function run(App $app, array $data = []);
}
