<?php

namespace Eraple;

abstract class Module
{
    protected static $name = '';

    protected static $description = 'No description found.';

    public static function getName()
    {
        return static::$name;
    }

    public static function getDescription()
    {
        return static::$description;
    }

    abstract public function registerTasks(App $app);
}
