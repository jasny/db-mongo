<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Model;

use MongoDB\BSON\Serializable;
use MongoDB\BSON\Unserializable;
use MongoDB\Model\BSONArray;
use function Jasny\array_without;
use function Jasny\is_associative_array;

/**
 * BSON representation of an associative array.
 * The array is not converted to an object, instead the keys are added to the value.
 */
class BSONAssoc extends BSONArray implements Serializable, Unserializable
{
    /**
     * Serialize the array to BSON.
     *
     * @return array
     */
    public function bsonSerialize(): array
    {
        $values = [];

        foreach ($this as $key => $value) {
            if ($value instanceof \stdClass || is_associative_array($value)) {
                $value = array_without((array)$value, ['__value']);
            } else {
                $value = ['__value' => $value];
            }

            $values[] = ['__key' => $key] + $value;
        }

        return $values;
    }

    /**
     * Unserialize the document to BSON.
     *
     * @param array $data
     * @return void
     */
    public function bsonUnserialize(array $data): void
    {
        foreach ($data as $index => $item) {
            $key = isset($item['__key']) ? $item['__key'] : $index;
            $value = isset($item['__value']) ? $item['__value'] : array_without($item, ['__key']);

            $this[$key] = $value;
        }
    }

    /**
     * Serialize the array to JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->getArrayCopy();
    }
}
