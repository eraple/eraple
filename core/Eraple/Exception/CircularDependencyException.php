<?php

namespace Eraple\Exception;

use Zend\Di\Exception\CircularDependencyException as ZendDiCircularDependencyException;

class CircularDependencyException extends ZendDiCircularDependencyException
{
}
