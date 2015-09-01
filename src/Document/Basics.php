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
trait Implementation
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
    protected function toData()
    {
        $values = static::castForDB($this->getValues());
        $data = static::mapToFields($values);
        
        if ($this instanceof Dataset\Sorted && method_exists(get_class($this), 'prepareDataForSort')) {
            $data += static::prepareDataForSort();
        }
        
        return $data;
    }

    
    /**
     * Save the document
     * 
     * @return $this
     */
    public function save()
    {
        if ($this instanceof Entity\LazyLoading && $this->isGhost()) {
            $msg = "This " . get_called_class() . " entity isn't fully loaded. First expand, than edit, than save.";
            throw new \Exception("Unable to save: $msg");
        }
        
        static::getCollection()->save($this->toData());
        return $this;
    }
    
    /**
     * Delete the document
     * 
     * @return $this
     */
    public function delete()
    {
        static::getCollection()->remove([static::getIdProperty() => $this->getId()]);
        return $this;
    }

    /**
     * Check no other document with the same value of the property exists
     * 
     * @param string $property
     * @return boolean
     */
    public function hasUnique($property)
    {
        if (!isset($this->$property)) return true;
        
        return !static::exists([
            static::getIdProperty() . '(not)' => $this->getId(),
            $property => $this->$property
        ]);
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
