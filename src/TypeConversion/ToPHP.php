<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\TypeConversion;

use MongoDB\BSON;
use MongoDB\Model\BSONArray;
use MongoDB\Model\BSONDocument;

/**
 * Convert MongoDB type to PHP type.
 */
class ToPHP implements ToPHPInterface
{
    /**
     * Convert BSONArray or BSONDocument to array or object
     *
     * @param BSONArray|BSONDocument $value
     * @param int                    $depth
     * @return array|object
     */
    protected function convertBsonModel($value, $depth)
    {
        $out = [];

        foreach ($value as $key => $item) {
            if (strpos($key, '\\\\') !== false || strpos($key, '\\u') !== false) {
                $key = json_decode('"' . addcslashes($key, '"') . '"');
            }

            $out[$key] = $this->convertValue($item, $depth + 1); // Recursion
        }

        return $value instanceof BSONDocument ? (object)$out : $out;
    }

    /**
     * Convert MongoDB type to PHP type, with recursion protection
     *
     * @param mixed $value
     * @param int   $depth
     * @return mixed
     */
    protected function convertValue($value, $depth = 0)
    {
        switch (true) {
            case is_scalar($value):
                return $value;

            case $value instanceof BSONArray;
            case $value instanceof BSONDocument:
                return $this->convertBsonModel($value, $depth);

            case $value instanceof BSON\UTCDateTime:
                return $value->toDateTime();
            case $value instanceof BSON\ObjectId:
                return (string)$value;

            default:
                return $value;
        }
    }

    /**
     * Invoke conversion
     *
     * @param mixed $value
     * @return mixed
     */
    final public function __invoke($value)
    {
        return $this->convertValue($value);
    }
}
