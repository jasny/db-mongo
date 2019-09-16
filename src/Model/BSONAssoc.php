<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Model;

use Improved\IteratorPipeline\Pipeline;
use MongoDB\BSON\Serializable as BSONSerializable;
use MongoDB\BSON\Unserializable as BSONUnserializable;
use MongoDB\Model\BSONArray;

/**
 * BSON representation of an associative array.
 * The array is not converted to an object, instead the keys are added to the value.
 * @immutable
 */
class BSONAssoc extends BSONArray implements BSONSerializable, BSONUnserializable
{
    /**
     * Serialize the array to BSON.
     */
    public function bsonSerialize(): array
    {
        return Pipeline::with($this)
            ->map(function ($value, string $key) {
                if ($value instanceof \stdClass) {
                    $value = (array)$value;
                    unset($value['__value']);
                } elseif (self::isAssoc($value)) {
                    unset($value['__value']);
                } else {
                    $value = ['__value' => $value];
                }

                return ['__key' => $key] + $value;
            })
            ->values()
            ->toArray();
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

    /**
     * Is value an associative array.
     *
     * @param mixed $value
     * @return bool
     */
    protected static function isAssoc($value): bool
    {
        return is_array($value) && array_keys($value) !== array_keys(array_keys($value));
    }
}
