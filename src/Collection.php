<?php

namespace Jasny\DB\Mongo;

/**
 * MongoDB Collection which produces Document objects
 */
class Collection extends \MongoCollection
{
    /**
     * Collections that have been indexed
     * @var array
     */
    protected static $indexed = [];
    
    /**
     * Record class
     * @var string
     */
    protected $documentClass;

    /**
     * @param \MongoDB $db
     * @param string   $name
     * @param string   $documentClass
     */
    public function __construct(\MongoDB $db, $name, $documentClass = null)
    {
        $this->documentClass = $documentClass;
        parent::__construct($db, $name);
    }

    /**
     * Create indexes
     * 
     * @param array $indexes
     */
    public function createIndexes(array $indexes)
    {
        if (static::$indexed[$this->getName()] === $indexes) return;
        
        foreach ($indexes as $index) {
            $options = isset($index['$options']) ? $index['$options'] : [];
            unset($index['$options']);

            $this->createIndex($index, $options);
        }
        
        static::$indexed[$this->getName()] = $indexes;
    }
    
    /**
     * Creates an index on the specified field(s) if it does not already exist. 
     * 
     * Additinal options are available:
     *   delete: true - Delete index instead of creating it
     *   force: true  - Delete existing index if needed
     * 
     * @param array $keys
     * @param array $options
     */
    public function createIndex(array $keys, array $options = [])
    {
        // Drop instead of create
        if (!empty($options['delete'])) return $this->deleteIndex($keys);
        
        // BC
        $fn = method_exists(get_parent_class(), 'createIndex') ? 'createIndex' : 'ensureIndex';

        try {
            $ret = call_user_func(['MongoCollection', $fn], $keys, $options);
        } catch (\MongoCursorException $e) {
            if (empty($options['force']) || $e->getCode() != 85) throw $e;

            $this->deleteIndex($keys);
            call_user_func(['MongoCollection', $fn], $keys, $options);
        }
        
        return $ret;
    }

    /**
     * Get the document class associated with this collection
     * 
     * @return string
     */
    public function getDocumentClass()
    {
        return $this->documentClass;
    }
    
    /**
     * Saves a document to this collection
     * 
     * @param array|object $doc      Array or object to save.
     * @param array        $options  Options for the save.
     * @return mixed
     */
    public function save(&$doc, array $options = [])
    {
        $values = $this->db->toMongoType($doc);
        $ret = parent::save($values, $options);
        
        if (is_array($doc)) {
            $doc['_id'] = $values['_id'];
        } else {
            $doc->_id = $values['_id'];
        }
        
        return $ret;
    }

    /**
     * Saves a document to this collection
     * 
     * @param array $docs     Array of arrays or objects to save.
     * @param array $options  Options for the save.
     * @return mixed
     */
    public function batchInsert(&$docs, array $options = [])
    {
        $rows = [];
        foreach ($docs as $i => $doc) {
            $rows[$i] = $this->db->toMongoType($doc);
        }
        
        $ret = parent::save($rows, $options);
        
        foreach ($rows as $i => $row) {
            if (is_array($doc)) {
                $docs[$i]['_id'] = $row['_id'];
            } else {
                $docs[$i]->_id = $row['_id'];
            }
        }
        
        return $ret;
    }
    
    
    /**
     * Convert values to a document
     * 
     * @param array $values
     * @return Record
     */
    public function asDocument(array $values)
    {
        $object = $this->db->fromMongoType($values);
        if (!isset($this->documentClass)) return $object;
        
        $class = $this->documentClass;
        return $class::instantiate($object);
    }

    
    /**
     * Queries this collection.
     * The cursor will return Document objects, unless you specify fields.
     * 
     * @param array $query   Search query
     * @param array $fields  Fields to return
     * @param array $sort    Fields to sort on
     * @param int   $limit   Specifies an upper limit to the number returned
     * @param int   $skip    Specifies a number of results to skip before starting the count
     * @return Cursor
     */
    public function find(array $query = [], array $fields = [], $sort = null, $limit = null, $skip = null)
    {
        $cursor = new Cursor($this->db->getClient(), $this, $query, $fields);
        
        if (isset($sort)) $cursor->sort($sort);
        if (isset($limit)) $cursor->limit($limit);
        if (isset($skip)) $cursor->skip($skip);
        
        return $cursor;
    }
    
    /**
     * Queries this collection, returning a single element.
     * Returns a Document object, unless you specify fields.
     * 
     * @param array $query   Fields for which to search
     * @param array $fields  Fields of the results to return
     * @return array|Document
     */
    public function findOne($query = [], array $fields = [])
    {
        $values = parent::findOne($query, $fields);
        return isset($values) && empty($fields) ? $this->asDocument($values) : $values;
    }
    
    /**
     * Counts the number of documents in this collection.
     * 
     * @param array|\MongoId $query  The fields for which to search
     * @param int            $limit  Specifies an upper limit to the number returned
     * @param int            $skip   Specifies a number of results to skip before starting the count
     * @return int
     */
    public function count(array $query = [], $limit = 0, $skip = 0)
    {
        if ($query instanceof \MongoId) $query = ['_id' => $query];
        if (is_string($query)) $query = ['_id' => new \MongoId($query)];
        
        return parent::count($query, $limit, $skip);
    }
}
