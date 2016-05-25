<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Mongo\Cursor;
use Jasny\DB\Entity;

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
     * Entity class
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
        if (isset($documentClass) && !is_a($documentClass, Entity::class, true)) {
            throw new \LogicException("Class $documentClass is not a " . Entity::class);
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
        foreach ($indexes as $index) {
            $options = isset($index['$options']) ? $index['$options'] : [];
            unset($index['$options']);

            if (!empty($options['delete'])) {
                $this->deleteIndex($index);
                continue;
            }

            $this->createIndex($index, $options);
        }
    }
    
    /**
     * Creates an index on the specified field(s) if it does not already exist. 
     * 
     * Additinal options are available:
     *   ignore: true - No error if the index already exists
     *   force: true  - Delete existing index if needed
     * 
     * @param array $keys
     * @param array $options
     * @return boolean
     */
    public function createIndex(array $keys, array $options = [])
    {
        $ret = false;

        // BC
        $fn = method_exists(\MongoCollection::class, 'createIndex') ? 'createIndex' : 'ensureIndex';

        try {
            $ret = call_user_func([\MongoCollection::class, $fn], $keys, $options);
        } catch (\MongoCursorException $e) {
            if ($e->getCode() != 85 || (empty($options['ignore']) && empty($options['force']))) throw $e;
            
            if (!empty($options['force'])) {
                $this->deleteIndex($keys);
                call_user_func([\MongoCollection::class, $fn], $keys, $options);
            }
        }
        
        return $ret;
    }
    
    
    /**
     * Get a Collection object where casting to a document object is disabled
     *
     * @return static
     */
    public function withoutCasting()
    {
        return new static($this->db, $this->getName());
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
     * @param boolean $lazy
     * @return Entity
     */
    public function asDocument(array $values, $lazy = false)
    {
        if (!isset($this->documentClass)) {
            throw new \LogicException("Document class not set");
        }
        
        $class = $this->documentClass;
        if (!is_a($class, Entity::class, true)) {
            throw new \LogicException("Document class should implement the " . Entity::class . " interface");
        }

        if ($lazy && !is_a($class, Entity\LazyLoading::class, true)) {
            $msg = Entity::class . " doesn't support lazy loading. All fields are required to create the entity.";
            throw new \LogicException($msg);
        }
        
        foreach ($values as &$value) {
            $value = $this->db->fromMongoType($value);
        }
        
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
        
        if (isset($this->documentClass) && isset($values)) {
            $values = $this->asDocument($values, !empty($fields));
        }
        
        return $values;
    }
}

