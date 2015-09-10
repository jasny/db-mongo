<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\Entity,
    Jasny\DB\FieldMapping,
    Jasny\DB\Mongo\Dataset;

/**
 * Static methods to interact with a collection (as document)
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
trait BasicImplementation
{
    use Entity\Implementation,
        FieldMapping\Implementation,
        Dataset\Implementation
    {
        Entity\Implementation::fromData as private _entity_fromData;
    }
    
    
    /**
     * Get the field map.
     * 
     * @return array
     */
    protected static function getFieldMap()
    {
        return ['_id' => static::getIdProperty()];
    }
    
    /**
     * Get the property used to identify the document
     * 
     * @return string
     */
    public static function getIdProperty()
    {
        return 'id';
    }

    /**
     * Get document id.
     * 
     * @return string
     */
    public function getId()
    {
        $prop = static::getIdProperty();
        return $this->$prop;
    }
    
    /**
     * Get the data that needs to be saved in the DB
     * 
     * @return array
     */
    public function toData()
    {
        $values = $this->getValues();
        $casted = static::castForDB($values);
        $data = static::mapToFields($casted);
        
        if ($this instanceof Dataset\Sorted && method_exists(get_class($this), 'prepareDataForSort')) {
            $data += static::prepareDataForSort();
        }
        
        if (array_key_exists('_id', $data) && is_null($data['_id'])) unset($data['_id']);
        return $data;
    }

    
    /**
     * Save the document
     * 
     * @param array $opts
     * @return $this
     */
    public function save(array $opts = [])
    {
        if ($this instanceof Entity\LazyLoading && $this->isGhost()) {
            $msg = "This " . get_called_class() . " entity isn't fully loaded. First expand, than edit, than save.";
            throw new \Exception("Unable to save: $msg");
        }
        
        $data = $this->toData();
        static::getCollection()->save($data);
        
        $idProp = static::getIdProperty();
        $this->$idProp = $data['_id'];
        $this->cast();
        
        return $this;
    }
    
    /**
     * Delete the document
     * 
     * @param array $opts
     * @return $this
     */
    public function delete(array $opts = [])
    {
        static::getCollection()->remove([static::getIdProperty() => $this->getId()]);
        return $this;
    }

    /**
     * Reload the entity from the DB
     * 
     * @param array $opts
     * @return $this
     */
    public function reload(array $opts = [])
    {
        $entity = static::fetch($this, $opts);

        foreach ((array)$entity as $prop => $value) {
            if ($prop[0] === "\0") continue; // Ignore private and protected properties
            $this->$prop = $value;
        }
        
        return $this;
    }
    
    /**
     * Check no other document with the same value of the property exists
     * 
     * @param string        $property
     * @param array|string  $group     List of properties that should match
     * @param array         $opts
     * @return boolean
     */
    public function hasUnique($property, $group = null, array $opts = [])
    {
        if (!isset($this->$property)) return true;
        
        $filter = [static::getIdProperty() . '(not)' => $this->getId(), $property => $this->$property];
        foreach ((array)$group as $prop) {
            if (isset($this->$prop)) $filter[$prop] = $this->$prop;
        }
        
        return !static::exists($filter, $opts);
    }
    
    
    /**
     * Prepare result when casting object to JSON
     * 
     * @return object
     */
    public function jsonSerialize()
    {
        $this->expand();
        
        $values = $this->getValues();
        
        foreach ($values as &$value) {
            if ($value instanceof \DateTime) $value = $value->format(\DateTime::ISO8601);
            if ($value instanceof \MongoId) $value = (string)$value;
        }
        
        return $this->jsonSerializeFilter((object)$values);
    }
    
    /**
     * Convert loaded values to an entity
     * 
     * @param array $values
     * @return static
     */
    public static function fromData($values)
    {
        $mapped = static::mapFromFields($values);
        return static::_entity_fromData($mapped);
    }
}
