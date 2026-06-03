<?php

declare(strict_types=1);

namespace Fulcrum\Container\Exceptions;

use Psr\Container\ContainerExceptionInterface;

class ContainerException extends \RuntimeException implements ContainerExceptionInterface {}
