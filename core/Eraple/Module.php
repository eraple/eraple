<?php

namespace Eraple;

abstract class Module
{
    /**
     * Module name.
     *
     * @var string
     */
    protected static $name = '';

    /**
     * Module description.
     *
     * @var string
     */
    protected static $description = 'No description found.';

    /**
     * Get the module name.
     *
     * @return string
     */
    public static function getName()
    {
        return static::$name;
    }

    /**
     * Get the module description.
     *
     * @return string
     */
    public static function getDescription()
    {
        return static::$description;
    }

    /**
     * Register module tasks.
     *
     * @param App $app Application instance
     *
     * @return mixed
     */
    abstract public function registerTasks(App $app);
}
