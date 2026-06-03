<?php

declare(strict_types=1);

namespace Fulcrum\Container\Exceptions;

use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends ContainerException implements NotFoundExceptionInterface {}
