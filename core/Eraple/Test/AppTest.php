<?php

namespace Eraple\Test;

use Eraple\App;

class AppTest extends \PHPUnit\Framework\TestCase
{
    public function testRun()
    {
        $rootPath = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'app';
        App::instance($rootPath)->run();

        $this->assertTrue(true);
    }
}
