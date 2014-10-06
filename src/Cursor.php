<?php

namespace DB;

use DB;

/**
 * MongoDB cursor that produces Records
 * 
 * @internal Intended as part of Jasny\DB. However version 2 isn't stable enough, so adding this lib standalone.
 */
class Cursor extends \MongoCursor
{
    /**
     * Record class
     * @var Collection
     */
    protected $collection;

    /**
     * @param \MongoClient      $connection
     * @param Collection|string $ns
     * @param array             $query
     * @param array             $fields
     */
    public function __construct(\MongoClient $connection, $ns, array $query = [], array $fields = [])
    {
        if ($ns instanceof Collection && empty($fields)) $this->collection = $ns;
        parent::__construct($connection, (string)$ns, $query, $fields);
    }

    /**
     * Get the record class associated with this cursor
     * 
     * @return string
     */
    public function getCollection()
    {
        return $this->collection;
    }
    
    /**
     * Returns the current element
     * 
     * @return array|object
     */
    public function current()
    {
        $values = parent::current();
        if (!isset($values)) return;
        
        return isset($this->collection) && isset($values['_id']) ?
            $this->collection->asDocument($values) :
            DB::fromMongoType($values);
    }
    
    /**
     * Return the next object to which this cursor points, and advance the cursor
     * 
     * @return array|object
     */
    public function getNext()
    {
        $this->next();
        return $this->current();
    }
}
