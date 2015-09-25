<?php

namespace Jasny\DB\Mongo\Document;

use Jasny\DB\Entity,
    Doctrine\Common\Inflector\Inflector;

/**
 * Document implementation with Introspection and TypedObject implementation
 */
trait MetaImplementation
{
    use BasicImplementation,
        Entity\Meta\Implementation,
        Entity\Validation\MetaImplementation
    {
        BasicImplementation::setValues as private _basic_setValues;
        Entity\Meta\Implementation::castValueToClass as private _entityMeta_castValueToClass;
        Entity\Meta\Implementation::entitySet insteadof BasicImplementation;
    }
    
    /**
     * Get the database connection
     * 
     * @return \Jasny\DB
     */
    protected static function getDB()
    {
        $name = static::meta()['db'] ?: 'default';
        return \Jasny\DB::conn($name);
    }
    
    /**
     * Get the Mongo collection name.
     * 
     * @return string
     */
    protected static function getCollectionName()
    {
        if (isset(static::$collection)) {
            $name = static::$collection;
        } elseif (isset(self::meta()['dbCollection'])) {
            $name = self::meta()['dbCollection'];
        } else {
            $class = preg_replace('/^.+\\\\/', '', static::getDocumentClass());
            $plural = Inflector::pluralize($class);
            $name = Inflector::tableize($plural);
        }
        
        return $name;
    }
    
    /**
     * Cast data to use in DB
     * 
     * @param array $data
     * @return array
     */
    protected static function castForDB($data)
    {
        foreach ($data as $key => &$value) {
            $prop = trim(strstr($key, '(', true)) ?: $key; // Remove filter directives
            $meta = static::meta()->{$prop};
            
            if (isset($meta['dbSkip'])) {
                unset($data[$key]);
            } elseif (isset($meta['dbFieldType'])) {
                $value = static::castValue($value, $meta['dbFieldType']);
            }
        }
        
        return $data;
    }
    
    /**
     * Get identifier property
     * 
     * @return string
     */
    public static function getIdProperty()
    {
        foreach (static::meta()->ofProperties() as $prop => $meta) {
            if (isset($meta['id'])) return $prop;
        }
        
        return 'id';
    }
    
    /**
     * Get the field map.
     * 
     * @return array
     */
    protected static function getFieldMap()
    {
        $fieldMap = ['_id' => static::getIdProperty()];
        
        foreach (static::meta()->ofProperties() as $prop => $meta) {
            if (!isset($meta['dbFieldName']) || $meta['dbFieldName'] === $prop) continue;
            $fieldMap[$meta['dbFieldName']] = $prop;
        }
        
        return $fieldMap;
    }
    
    /**
     * Set the values.
     * 
     * @param array|object $values
     * @return $this
     */
    public function setValues($values)
    {
        $this->_basic_setValues($values);
        $this->cast();
        
        return $this;
    }
    
    /**
     * Filter object for json serialization
     * 
     * @param stdClass $object
     * @return stdClass
     */
    protected function jsonSerializeFilter($object)
    {
        foreach ($object as $prop => $value) {
            if (static::meta()->of($prop)['ignore']) {
                unset($object->$prop);
            }
        }
        
        return $object;
    }
    
    /**
     * Cast value to a non-internal type
     * 
     * @param mixed  $value
     * @param string $type
     * @return Entity|object
     */
    protected static function castValueToClass($value, $type)
    {
        if (is_null($value)) return $value;
        
        if (strtolower(ltrim($type, '\\')) === 'mongoid' && !$value instanceof \MongoId) {
            if (!is_string($value)) {
                trigger_error("Unable to cast " . gettype($value) . " to a MongoId.", E_USER_WARNING);
                return $value;
            }
            
            if (!ctype_xdigit($value) || strlen($value) != 24) {
                trigger_error("Unable to cast string '$value' to a MongoId.", E_USER_WARNING);
                return $value;
            }
        }
        
        return static::_entityMeta_castValueToClass($value, $type);
    }
}
