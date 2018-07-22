<?php

namespace Eraple\Core\Test\Unit\Data\Stub;

use Eraple\Core\App;
use Eraple\Core\Task;

class InvalidNameTask extends Task
{
    protected static $name = 'Sample Task';

    public function run(App $app, array $data = [])
    {
    }
}
