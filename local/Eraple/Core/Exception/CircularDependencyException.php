<?php

namespace Eraple\Core\Exception;

use Psr\Container\ContainerExceptionInterface;

class CircularDependencyException extends \Exception implements ContainerExceptionInterface
{
}
