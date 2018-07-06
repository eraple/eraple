<?php

namespace Eraple;

abstract class Task
{
    protected static $name = '';

    protected static $description = 'No description found.';

    protected static $position = 'event_before_end';

    protected static $priority = 0;

    public static function getName()
    {
        return static::$name;
    }

    public static function getDescription()
    {
        return static::$description;
    }

    public static function getPosition()
    {
        return static::$position;
    }

    public static function getPriority()
    {
        return static::$priority;
    }

    abstract public function run(App $app, array $data = []);
}
