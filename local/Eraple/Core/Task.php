<?php

namespace Eraple\Core;

/**
 * @method null|array run(...$parameters) Run task.
 */
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
     * @var array
     */
    protected static $events = 'before-end';

    /**
     * Task index.
     *
     * @var int
     */
    protected static $index = 0;

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
    public static function getName(): string
    {
        return static::$name;
    }

    /**
     * Get the task description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return static::$description;
    }

    /**
     * Get the task event.
     *
     * @return array
     */
    public static function getEvents(): array
    {
        return is_array(static::$events) ? static::$events : [static::$events];
    }

    /**
     * Get the task index.
     *
     * @return int
     */
    public static function getIndex(): int
    {
        return static::$index;
    }

    /**
     * Get the task services.
     *
     * @return array
     */
    public static function getServices(): array
    {
        return static::$services;
    }
}
