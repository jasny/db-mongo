<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\TypeConversion;

use Improved as i;

/**
 * Base class for type conversion between PHP and MongoDB types.
 */
abstract class AbstractTypeConversion
{
    /**
     * Convert value to MongoDB type, with recursion protection.
     *
     * @param mixed $value
     * @param int   $depth
     * @return mixed
     */
    abstract protected function convertValue($value, int $depth = 0);

    /**
     * Recursively convert BSON to PHP
     *
     * @param iterable|\stdClass $item
     * @param int $depth
     * @return iterable|\stdClass
     */
    protected function applyRecursive($item, int $depth = 0)
    {
        if ($depth >= 32) {
            throw new \OverflowException("Unable to convert value; possible circular reference");
        }

        $iterable = $item instanceof \stdClass ? (array)$item : $item;

        $generator = i\iterable_map($iterable, function ($value) use ($depth) {
            return $this->convertValue($value, $depth + 1); // recursion
        });

        if ($item instanceof \Traversable) {
            return $generator;
        }

        $array = i\iterable_to_array($generator, true);

        return $item instanceof \stdClass ? (object)$array : $array;
    }
}
