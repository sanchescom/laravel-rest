<?php

declare(strict_types=1);

namespace Sanchescom\Rest;

use Illuminate\Support\Collection as BaseCollection;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @extends BaseCollection<TKey, TValue>
 */
class Collection extends BaseCollection {}
