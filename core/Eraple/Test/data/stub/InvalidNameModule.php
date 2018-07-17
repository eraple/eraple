<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Module;

class InvalidNameModule extends Module
{
    protected static $name = 'Sample Module';

    public function registerTasks(App $app)
    {
    }
}
