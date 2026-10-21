<?php

declare(strict_types=1);

namespace Mattdi\Fayda\Laravel\Events;

use Mattdi\Fayda\Core\Exceptions\FaydaException;

final class FaydaLoginFailed
{
    public function __construct(public readonly FaydaException $exception) {}
}
