<?php

namespace Eraple\Core;

/**
 * @method null init(...$parameters) Initialize module.
 */
abstract class Module
{
    /**
     * Module name.
     *
     * @var string
     */
    protected static $name = '';

    /**
     * Module version.
     *
     * @var string
     */
    protected static $version = '';

    /**
     * Module description.
     *
     * @var string
     */
    protected static $description = 'No description found.';

    /**
     * Module tasks.
     *
     * @var Task[]
     */
    protected static $tasks = [];

    /**
     * Get the module name.
     *
     * @return string
     */
    public static function getName(): string
    {
        return static::$name;
    }

    /**
     * Get the module version.
     *
     * @return string
     */
    public static function getVersion(): string
    {
        return static::$version;
    }

    /**
     * Get the module description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return static::$description;
    }

    /**
     * Get the module tasks.
     *
     * @return Task[]
     */
    public static function getTasks(): array
    {
        return static::$tasks;
    }
}
