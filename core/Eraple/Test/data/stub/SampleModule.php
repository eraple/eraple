<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;
use Eraple\Module;

class SampleModule extends Module
{
    protected static $name = 'sample-module';

    public function registerTasks(App $app)
    {
    }
}
