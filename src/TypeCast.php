<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Entity,
    MongoDB\BSON;

/**
 * Type casting for MongoDB
 */
class TypeCast extends \Jasny\DB\TypeCast
{
    /**
     * Cast value to a class object
     *
     * @param string $type
     * @return Entity|\MongoDB\BSON\ObjectId|object
     */
    public function toClass($type)
    {
        if (is_null($this->value)) {
            return $this->value;
        }

        if (strtolower(ltrim($type, '\\')) === 'mongodb\\bson\\objectid' && !$this->value instanceof BSON\ObjectId) {
            if ($this->value instanceof Entity\Identifiable) {
                return $this->forValue($this->value->getId())->to(BSON\ObjectId::class);
            }

            return is_string($this->value) && ctype_xdigit($this->value) && strlen($this->value) === 24
                ? new BSON\ObjectId($this->value)
                : $this->dontCastTo(BSON\ObjectId::class);
        }

        return parent::toClass($type);
    }
}
