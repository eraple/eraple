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
     * Task event.
     *
     * @var string
     */
    protected static $event = 'event_before_end';

    /**
     * Task priority.
     *
     * @var int
     */
    protected static $priority = 0;

    /**
     * Task services.
     *
     * @var array
     */
    protected static $services = [];

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
     * Get the task event.
     *
     * @return string
     */
    public static function getEvent()
    {
        return static::$event;
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
     * Get the task services.
     */
    public static function getServices()
    {
        return static::$services;
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
