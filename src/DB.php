<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Connection, Jasny\DB\Entity, Jasny\DB\Blob;

/**
 * Instances of this class are used to interact with a Mongo database.
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.com/db-mongo
 */
class DB extends \MongoDB implements Connection, Connection\Namable
{
    use Connection\Named;
    
    const ASCENDING = 1;
    const DESCENDING = -1;
    
    /**
     * @var MongoClient
     */
    protected $_mongoClient;
    
    /**
     * @param \MongoClient $conn
     * @param string       $name
     */
    public function __construct(MongoClient $conn, $name)
    {
        parent::__construct($conn, $name);
        $this->_mongoClient = $conn;
    }
    
    /**
     * Get the client connection
     * 
     * @return MongoClient
     */
    public function getClient()
    {
        return $this->_mongoClient;
    }
    
    /**
     * Gets a collection
     * 
     * @param string $name
     * @param string $recordClass
     * @return DB\Collection
     */
    public function selectCollection($name, $recordClass = null)
    {
        return new Collection($this, $name, $recordClass);
    }

    
    /**
     * Convert value to mongo type
     * 
     * @param mixed $value
     * @return mixed
     */
    public static function toMongoType($value)
    {
        if ($value instanceof Entity) {
            $value = $value->getValues();
        }
        
        return static::propertyToMongoType($value);
    }
    
    /**
     * Convert property to mongo type
     * 
     * @param mixed $value
     * @return mixed
     */
    protected static function propertyToMongoType($value)
    {
        if (isset($value->_id) && $value->_id instanceof \MongoId) {
            return $value->_id;
        }
        
        if (is_array($value) && count($value) === 1 && key($value) === '$id') {
            return new \MongoId($value['$id']);
        }
        
        if ($value instanceof Entity\Identifiable) {
            return $value->getId();
        }
        
        if ($value instanceof \DateTime) {
            return new \MongoDate($value->getTimestamp());
        }
        
        if ($value instanceof Blob) {
            return \MongoBinData($value, \MongoBinData::GENERIC);
        }
        
        if (is_array($value) || is_object($value)) {
            foreach ($value as &$v) {
                $v = static::propertyToMongoType($v);
            }
            return $value;
        }
        
        return $value;
    }
    
    /**
     * Convert mongo type to value
     * 
     * @param mixed $value
     * @return mixed
     */
    public static function fromMongoType($value)
    {
        if (is_array($value) || $value instanceof stdClass) {
            $isNumeric = is_array($value) && (key($value) === 0 &&
                array_keys($value) === array_keys(array_fill(0, count($value), null))) || !count($value);
            
            $out = !$isNumeric ? (object)$value : $value;
            
            foreach ($out as &$var) {
                $var = self::fromMongoType($var);
            }
            
            return $out;
        }
        
        if ($value instanceof \MongoDate) {
            return \DateTime::createFromFormat('U', $value->sec);
        }
        
        if ($value instanceof \MongoBinData) {
            return new Blob($value->bin);
        }
        
        return $value;
    }
}
