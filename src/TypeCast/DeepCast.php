<?php

namespace Jasny\DB\Mongo\TypeCast;

use Jasny\DB\Entity,
    Jasny\DB\Blob,
    Jasny\DB\EntitySet,
    MongoId,
    MongoDB\BSON;

/**
 * Type casting for MongoDB, using recursive casting
 *
 * Do not move this methods to TypeCast, to avoid it's instance creation when doing recursive casting
 */
class DeepCast
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
     * Convert property to mongo type.
     * Works recursively for objects and arrays.
     *
     * @param mixed   $value
     * @param boolean $escapeKeys  Escape '.' and '$'
     * @return mixed
     */
    public function toMongoType($value, $escapeKeys = false)
    {
        if ($this->isMongoType($value)) {
            return $value;
        }

        if ($value instanceof \DateTime) {
            return new BSON\UTCDateTime($value->getTimestamp() * 1000);
        }

        if ($value instanceof Blob) {
            return new BSON\Binary($value, BSON\Binary::TYPE_GENERIC);
        }

        if ($value instanceof Entity\Identifiable) {
            $data = $value->toData();
            return isset($data['_id']) ? $data['_id'] : $value->getId();
        }

        if ($value instanceof Entity) {
            $value = $value->toData();
        }

        if ($value instanceof \ArrayObject || $value instanceof EntitySet) {
            $value = $value->getArrayCopy();
        }

        if (is_object($value) && !$value instanceof \stdClass) {
            throw new \MongoDB\Exception\InvalidArgumentException("Don't know how to cast a " . get_class($value) . " object to a mongo type");
        }

        if (is_array($value) || $value instanceof \stdClass) {
            $copy = [];

            foreach ($value as $k => $v) {
                $key = $escapeKeys ? strtr($k, ['\\' => '\\\\', '$' => '\\u0024', '.' => '\\u002e']) : $k;
                $copy[$key] = $this->toMongoType($v, $escapeKeys); // Recursion
            }

            $value = is_object($value) ? (object)$copy : $copy;
        }

        return $value;
    }

    /**
     * Convert mongo type to value
     *
     * @param mixed   $value
     * @param boolean $translateKeys
     * @return mixed
     */
    public function fromMongoType($value)
    {
        if (is_array($value) || $value instanceof \stdClass) {
            $out = [];

            foreach ($value as $k => $v) {
                // Unescape special characters in keys
                $unescape = strpos($k, '\\\\') !== false || strpos($k, '\\u') !== false;
                $key = $unescape ? json_decode('"' . addcslashes($k, '"') . '"') : $k;

                $out[$key] = $this->fromMongoType($v); // Recursion
            }

            $isNumeric = is_array($value) &&
                (key($value) === 0 && array_keys($value) === array_keys(array_fill(0, count($value), null))) ||
                !count($value);

            return !$isNumeric ? (object)$out : $out;
        }

        if ($value instanceof BSON\UTCDateTime) {
            return $value->toDateTime();
        }

        if ($value instanceof BSON\Binary) {
            return new Blob($value->getData());
        }

        return $value;
    }
}
