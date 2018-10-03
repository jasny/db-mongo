<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\TypeConversion;

use Jasny\Entity\EntityInterface;
use Jasny\Entity\IdentifiableEntityInterface;
use MongoDB\BSON;
use MongoDB\Exception\InvalidArgumentException;

/**
 * Convert PHP type to MongoDB type.
 */
class ToMongo implements ToMongoInterface
{
    /**
     * Check if value is a MongoDB specific type
     *
     * @param mixed $value
     * @return boolean
     */
    protected function isMongoType($value)
    {
        return
            $value instanceof BSON\Binary ||
            $value instanceof BSON\UTCDateTime ||
            $value instanceof BSON\Decimal128 ||
            $value instanceof BSON\ObjectId ||
            $value instanceof BSON\MaxKey ||
            $value instanceof BSON\MinKey ||
            $value instanceof BSON\Regex ||
            $value instanceof BSON\Timestamp;
    }


    /**
     * Convert non scalar value to mongodb type.
     *
     * @param object|array $value
     * @param int          $depth  Recursion depth
     * @return array
     */
    protected function convertStructured($value, $depth): array
    {
        $copy = [];

        foreach ($value as $key => $item) {
            $mongoKey = strtr($key, ['\\' => '\\\\', '$' => '\\u0024', '.' => '\\u002e']);
            $copy[$mongoKey] = $this->convert($value, $depth + 1); // Recursion
        }

        return is_object($value) ? (object)$copy : $copy;
    }

    /**
     * Convert value to MongoDB type, with recursion protection.
     *
     * @param mixed $value
     * @param int   $depth
     * @return mixed
     */
    protected function convertValue($value, $depth = 0)
    {
        if ($depth >= 100) {
            throw new InvalidArgumentException("Unable to convert value to MongoDB type; possible circular reference");
        }

        switch (true) {
            case is_scalar($value):
            case $this->isMongoType($value):
            case $value instanceof BSON\Serializable:
                return $value;

            case $value instanceof \DateTimeInterface:
                return new BSON\UTCDateTime($value->getTimestamp() * 1000);

            case $value instanceof EntityInterface:
                $value = $value instanceof IdentifiableEntityInterface ? $value->getId() : $value->toAssoc();
                return $this->convertValue($value, $depth + 1); // Recursion

            case is_object($value) || is_array($value):
                return $this->convertStructured($value, $depth);

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
