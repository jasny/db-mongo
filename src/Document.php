<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Entity,
    Jasny\DB\FieldMapping;

/**
 * Base class for Mongo Documents
 */
abstract class Document implements
    Document\ActiveRecord,
    Entity\ChangeAware,
    Entity\Meta,
    FieldMapping,
    Entity\LazyLoading,
    Entity\Validation
{
    use Document\MetaImplementation,
        Entity\ChangeAware\Implementation,
        Document\LazyLoading\Implementation
    {
        reload as private _reload;
        save as private _save;
        fromData as private _fromData;
        lazyload as private _lazyload;
    }
    
    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->cast();
    }
    
    /**
     * Reload the entity
     * 
     * @param array $opts
     * @return $this
     */
    public function reload(array $opts = [])
    {
        $this->_reload($opts);
        $this->markAsPersisted();
        
        return $this;
    }
    
    /**
     * Save the document
     * 
     * @param array $opts
     * @return $this
     */
    public function save(array $opts = array())
    {
        $this->_save($opts);
        $this->markAsPersisted();
        
        return $this;
    }
    
    
    /**
     * Convert loaded values to an entity.
     * Calls the construtor *after* setting the properties.
     * 
     * @param object $values
     * @return static
     */
    public static function fromData($values)
    {
        $entity = static::_fromData($values);
        $entity->markAsPersisted();
        
        return $entity;
    }
    
    /**
     * Create a ghost object.
     * 
     * @param array|MongoId|mixed $values  Values or ID
     * @return static
     */
    public static function lazyload($values)
    {
        if ($values instanceof \stdClass) $values = (array)$values;
        if (is_array($values)) $values = static::mapFromFields($values);
        
        $entity = static::_lazyload($values);
        $entity->cast();
        
        return $entity;
    }
}
