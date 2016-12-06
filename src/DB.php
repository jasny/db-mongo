<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Connection,
    Jasny\DB\Entity,
    Jasny\DB\EntitySet,
    Jasny\DB\Blob;

/**
 * Instances of this class are used to interact with a Mongo database.
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.com/db-mongo
 */
class DB extends \MongoDB implements Connection, Connection\Namable
{
    use Connection\Namable\Implemention;
    
    const ASCENDING = 1;
    const DESCENDING = -1;
    
    /**
     * @var MongoClient
     */
    protected $mongoClient;
    
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
        $this->mongoClient = $client;
    }
    
    /**
     * Get the client connection
     * 
     * @return MongoClient
     */
    public function getClient()
    {
        return $this->mongoClient;
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
     * Get's a collection
     * 
     * @param string $name
     * @return Collection
     */
    public function __get($name)
    {
        return $this->selectCollection($name);
    }
    
    
    /**
     * Get translation for reserved characters in keys
     * 
     * @return array
     */
    protected static function getKeyTranslations()
    {
        $dot = html_entity_decode("&#10050;"); // Circled open centre eight pointed star
        $dollar = html_entity_decode('&#9812;'); // Chess king

        return ['.' => $dot, '$' => $dollar];
    }
    
    /**
     * Convert property to mongo type.
     * Works recursively for objects and arrays.
     * 
     * @param mixed $value
     * @return mixed
     */
    public static function toMongoType($value)
    {
        if ($value instanceof \DateTime) {
            return new \MongoDate($value->getTimestamp());
        }
        
        if ($value instanceof Blob) {
            return \MongoBinData($value, \MongoBinData::GENERIC);
        }
        
        if ($value instanceof Entity\Identifiable) {
            $data = $value->toData();
            return isset($data['_id']) ? $data['_id'] : $value->getId();
        }
        
        if ($value instanceof Entity) $value = $value->toData();
        
        if ($value instanceof \ArrayObject || $value instanceof EntitySet) {
            $value = $value->getArrayCopy();
        } 
        
        if (is_array($value) || is_object($value)) {
            $copy = [];
            
            
            foreach ($value as $k => $v) {
                $key = strtr($k, static::getKeyTranslations());
                $copy[$key] = static::toMongoType($v); // Recursion
            }
            
            $value = is_object($value) ? (object)$copy : $copy;
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
        if (is_array($value) || $value instanceof \stdClass) {
            $out = [];
            
            foreach ($value as $k => $v) {
                $key = strtr($k, array_flip(static::getKeyTranslations()));
                $out[$key] = self::fromMongoType($v); // Recursion
            }
            
            $isNumeric = is_array($value) && (key($value) === 0 &&
                array_keys($value) === array_keys(array_fill(0, count($value), null))) || !count($value);
            
            return !$isNumeric ? (object)$out : $out;
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
            $value = static::toMongoType($filterVal);
            
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
     * Convert a Jasny DB styled sort array to a MongoDB sort.
     * 
     * @param array $sort
     * @return array
     */
    public static function sortToQuery($sort)
    {
        $query = [];
        
        foreach ($sort as $key) {
            $order = self::ASCENDING;
            
            if ($key[0] === '^') {
                $key = substr($key, 1);
                $order = self::DESCENDING;
            }
            
            if ($key[0] === '$') throw new \Exception("Invalid sort key '$key'. Starting with '$' isn't allowed.");
            
            $query[$key] = $order;
        }
        
        return $query;
    }
}
