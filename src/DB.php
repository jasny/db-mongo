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
     * @param \MongoClient|string|array $client  Client or settings
     * @param string                    $name
     */
    public function __construct($client, $name = null)
    {
        if (is_array($client) || (is_object($client) && !$client instanceof \MongoClient)) {
            $options = (array)$client;
            $name = isset($options['database']) ? $options['database'] : null;
            
            $server = $options['client'];
            if (!strpos($options['client'], '/') && isset($name)) $server .= '/' . $name;
            
            unset($options['client'], $options['database']);
            
            $client = new \MongoClient($server, $options);
        }
        
        if (is_string($client)) $client = new \MongoClient($client);
        
        parent::__construct($client, $name);
        $this->_mongoClient = $client;
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
        if ($value instanceof Entity\Identifiable) {
            if (isset($value->_id) && $value->_id instanceof \MongoId) {
                return $value->_id;
            }
            
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
    
    /**
     * Convert a Jasny DB styled filter to a MongoDB query.
     * 
     * @param array $filter
     * @return array
     */
    public static function filterToQuery($filter)
    {
        $query = [];
        
        foreach ($filter as $key => $filterVal) {
            if ($key[0] === '$') throw new \Exception("Invalid filter key '$key'. Starting with '$' isn't allowed.");
            
            list($field, $operator) = array_map('trim', explode('(', str_replace(')', '', $key))) + [1 => null];
            $value = static::propertyToMongoType($filterVal);
            
            switch ($operator) {
                case '':     $query[$field] = $value; break;
                case 'not':  $query[$field] = ['$ne' => $value]; break;
                case 'min':  $query[$field] = ['$gte' => $value]; break;
                case 'max':  $query[$field] = ['$lte' => $value]; break;
                case 'any':  $query[$field] = ['$in' => $value]; break;
                case 'none': $query[$field] = ['$nin' => $value]; break;
                case 'all':  $query[$field] = ['$all' => $value]; break;
            
                default: throw new \Exception("Invalid filter key '$key'. Unknown operator '$operator'.");
            }
        }
        
        return $query;
    }
    
    /**
     * Get's a collection
     * 
     * @param string $name
     * @return Collection
     */
    public function __get($name)
    {
        return $this->selectCollection($name);
    }
}
