<?php

namespace Jasny\DB\Mongo\Document\LazyLoading;

use Jasny\DB\Entity\LazyLoading\Implementation as Base,
    Jasny\DB\Entity\Identifiable;

/**
 * Implementation for LazyLoading interface for MongoDB documents / entities
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db/master/LICENSE MIT
 * @link    https://jasny.github.com/db
 */
trait Implementation
{
    use Base {
        lazyload as _entity_lazyload;
    }
    
    /**
     * Create a ghost object.
     * 
     * @param array|MongoId|mixed $values  Values or ID
     * @return static
     */
    public static function lazyload($values)
    {
        $class = get_called_class();
        
        if ($values instanceof \MongoId) {
            if (!is_a($class, Identifiable::class, true)) {
                throw new Exception("Unable to lazy load a MongoId for $class: Identity property not defined");
            }
            
            $prop = static::getIdProperty();
            if (is_array($prop)) {
                throw new Exception("Unable to lazy load a MongoId for $class: Class has a complex identity");
            }
            
            $values = [$prop => $values];
        }
        
        return static::_entity_lazyload($values);
    }    
}
