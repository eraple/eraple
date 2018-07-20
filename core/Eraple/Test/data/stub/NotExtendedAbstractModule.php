<?php

namespace Eraple\Test\Data\Stub;

use Eraple\App;

class NotExtendedAbstractModule
{
    protected static $name = 'not-extended-abstract-module';

    public function registerTasks(App $app)
    {
    }
}
