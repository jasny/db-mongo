<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Common;

/**
 * Is value an associative array.
 *
 * @param mixed $value
 * @return bool
 */
function is_assoc($value): bool
{
    return is_array($value) && array_keys($value) !== array_keys(array_keys($value));
}
