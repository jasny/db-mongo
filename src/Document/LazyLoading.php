<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\Entity;

/**
 * Lazy loading implementation for Mongo documents
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait LazyLoading
{
    use Entity\SimpleLazyLoading {
        Entity\SimpleLazyLoading::lazyload as private lazyloadEntity;        
    }
    
    /**
     * Create a ghost object.
     * 
     * @param mixed|array $values  Unique ID or values
     * @return static
     */
    public static function lazyload($values)
    {
        if (is_string($values)) $values = ['_id' => new \MongoId($values)];
        if ($values instanceof \MongoId) $values = ['_id' => $values];
        
        return self::lazyloadEntity($values);
    }
}
