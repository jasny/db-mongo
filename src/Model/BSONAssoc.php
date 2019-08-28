<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\Model;

use MongoDB\BSON\Serializable;
use MongoDB\BSON\Unserializable;
use MongoDB\Model\BSONArray;

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
            if ($value instanceof \stdClass) {
                $value = (array)$value;
                unset($value['__value']);
            } elseif (is_array($value) && array_keys($value) !== array_keys(array_keys($value))) { // is assoc array
                unset($value['__value']);
            } else {
                $value = ['__value' => $value];
            }

            $values[] = ['__key' => $key] + $value;
        }

        return $values;
    }

    /**
     * Deserialize the document to BSON.
     *
     * @param array $data
     * @return void
     */
    public function bsonDeserialize(array $data): void
    {
        foreach ($data as $index => $item) {
            $key = isset($item['__key']) ? $item['__key'] : $index;

            if (isset($item['__value'])) {
                $value = $item['__value'];
            } else {
                $value = $item;
                unset($value['__key']);
            }

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
