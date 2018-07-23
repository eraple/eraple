<?php

namespace Eraple\Core\Exception;

use Psr\Container\NotFoundExceptionInterface;

class MissingParameterException extends \Exception implements NotFoundExceptionInterface
{
}
