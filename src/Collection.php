<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Mongo\Cursor;

/**
 * Mongo collection which produces Document objects
 * 
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
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
        if (isset($documentClass) && !is_a($documentClass, 'Jasny\DB\Entity', true)) {
            throw new \Exception("Class $documentClass is not a Jasny\DB\Entity");
        }
        
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
        // Prevent re-applying the same indexes this request
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
     * Convert values to a document
     * 
     * @param array $values
     * @return Entity
     */
    public function asDocument(array $values)
    {
        foreach ($values as &$value) {
            $value = $this->db->fromMongoType($value);
        }
        
        if (!isset($this->documentClass)) return (object)$values;
        
        $class = $this->documentClass;
        return $class::fromData($values);
    }

    
    /**
     * Queries this collection.
     * The cursor will return Document objects, unless you specify fields.
     * 
     * @param array $query   Search query
     * @param array $fields  Fields to return
     * @return Cursor
     */
    public function find(array $query = [], array $fields = [])
    {
        return new Cursor($this->db->getClient(), $this, $query, $fields);
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
}
