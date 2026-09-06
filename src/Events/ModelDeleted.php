<?php

declare(strict_types=1);

namespace Sanchescom\Rest\Events;

use Sanchescom\Rest\Model;

final class ModelDeleted
{
    public function __construct(public readonly Model $model) {}
}
