<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Entity;
use MongoId;

/**
 * Type casting for MongoDB
 */
class TypeCast extends \Jasny\DB\TypeCast
{
    /**
     * Cast value to a class object
     * 
     * @param string $type
     * @return Entity|MongoId|object
     */
    public function toClass($type)
    {
        if (is_null($this->value)) {
            return $this->value;
        }
        
        if (strtolower(ltrim($type, '\\')) === 'mongoid' && !$this->value instanceof MongoId) {
            if ($this->value instanceof Entity\Identifiable) {
                return $this->forValue($this->value->getId())->to(MongoId::class);
            }
            
            return is_string($this->value) && ctype_xdigit($this->value) && strlen($this->value) === 24
                ? new MongoId($this->value)
                : $this->dontCastTo(MongoId::class);
        }
        
        return parent::toClass($type);
    }
}
