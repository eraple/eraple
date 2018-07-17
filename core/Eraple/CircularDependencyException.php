<?php

namespace Eraple;

use Zend\Di\Exception\CircularDependencyException as ZendDiCircularDependencyException;

class CircularDependencyException extends ZendDiCircularDependencyException
{
}
