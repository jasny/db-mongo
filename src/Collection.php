<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Mongo\Cursor,
    Jasny\DB\Entity,
    MongoDB\Driver\Manager;

/**
 * Mongo collection which produces Document objects
 *
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
class Collection extends \MongoDB\Collection
{
    use ParentCallTestable;

    /**
     * Entity class
     * @var string
     */
    protected $documentClass;

    /**
     * @param \MongoDB\Driver\Manager  $manager
     * @param string                   $dbName
     * @param string                   $name
     * @param array                    $options
     */
    public function __construct(Manager $manager, $dbName, $name, $options = [])
    {
        $documentClass = isset($options['documentClass']) ? $options['documentClass'] : null;
        unset($options['documentClass']);

        if (isset($documentClass) && !is_a($documentClass, Entity::class, true)) {
            throw new \LogicException("Class $documentClass is not a " . Entity::class);
        }

        $this->documentClass = $documentClass;
        parent::__construct($manager, $dbName, $name, $options);
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
    public function createIndex($keys, array $options = [])
    {
        $ret = false;
        $keys = (array)$keys;

        try {
            $ret = $this->parent('createIndex', $keys, $options);
        } catch (\MongoDB\Driver\Exception\RuntimeException $e) {
            if ($e->getCode() != 85 || (empty($options['ignore']) && empty($options['force']))) { // 85 - index exists
                throw $e;
            }

            if (!empty($options['force'])) {
                $name = array_keys($keys)[0];
                $this->dropIndex($name);
                $ret = $this->parent('createIndex', $keys, $options);
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
        return new Collection(
            $this->getManager(),
            $this->getDatabaseName(),
            $this->getCollectionName()
        );
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
     * Replace a document in collection
     * @link https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-replaceOne/
     *
     * @param array|object $filter
     * @param array|object $doc      Array or object to save.
     * @param array        $options  Options for the save.
     * @return array|boolean
     */
    public function replaceOne($filter, $doc, array $options = [])
    {
        $typeCast = $this->getTypeCaster();
        $values = $typeCast->toMongoType($doc, true);

        return $this->parent('replaceOne', $filter, $values, $options);
    }

    /**
     * Insert a document to this collection
     * @link https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-insertOne/
     *
     * @param array|object $doc      Array or object to save.
     * @param array        $options  Options for the save.
     * @return array|boolean
     */
    public function insertOne($doc, array $options = [])
    {
        $typeCast = $this->getTypeCaster();
        $values = $typeCast->toMongoType($doc, true);

        return $this->parent('insertOne', $values, $options);
    }

    /**
     * Inserts multiple documents into this collection
     * @link https://docs.mongodb.com/php-library/current/reference/method/MongoDBCollection-insertMany/
     *
     * @param array $docs
     * @param array $options
     * @return mixed
     */
    public function insertMany(array $docs, array $options = [])
    {
        $data = [];
        $typeCast = $this->getTypeCaster();

        foreach ($docs as $doc) {
            $data[] = $typeCast->toMongoType($doc, true);
        }

        return $this->parent('insertMany', $data, $options);
    }

    /**
     * Saves a document to this collection
     *
     * @param array|object $doc      Array or object to save.
     * @param array        $options  Options for the save.
     * @return array|boolean
     */
    public function save($doc, array $options = [])
    {
        $typeCast = $this->getTypeCaster();
        $values = $typeCast->toMongoType($doc, true);
        $filter = $this->createReplaceOneFilter($values);

        if ($filter) {
            $options = array_merge(['upsert' => true], $options);
            return $this->replaceOne($filter, $doc, $options);
        }

        return $this->insertOne($doc, $options);
    }

    /**
     * Use result id for processed document
     *
     * @param array|object $doc
     * @param string       $idName       Name of document id property
     * @param object       $queryResult
     */
    public function useResultId(&$doc, $idName, $queryResult)
    {
        $id = null;

        if ($queryResult instanceof \MongoDB\InsertManyResult) {
            $id = $queryResult->getInsertedIds();
        } else if ($queryResult instanceof \MongoDB\UpdateResult) {
            $id = $queryResult->getUpsertedId();
        } else if ($queryResult instanceof \MongoDB\InsertOneResult) {
            $id = $queryResult->getInsertedId();
        }

        if (is_array($id)) {
            foreach ($id as $i => $oneId) {
                $this->setDocId($doc[$i], $idName, $oneId);
            }
        } else {
            $this->setDocId($doc, $idName, $id);
        }
    }

    /**
     * Update the identifier
     *
     * @param array|object           $doc
     * @param string                 $idName       Name of document id property
     * @param MongoDB\BSON\ObjectId  $id
     */
    protected function setDocId(&$doc, $idName, $id)
    {
        if (!$id) {
            return;
        }

        if (is_array($doc)) {
            $doc[$idName] = $id;
        } else {
            $doc->$idName = $id;
        }
    }

    /**
     * Create filter for replacing document
     *
     * @param array|object $values
     * @return array
     */
    protected function createReplaceOneFilter($values)
    {
        $array = (array)$values;
        $filter = array_intersect_key($array, ['_id' => 1]);

        return $filter;
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

        $typeCast = $this->getTypeCaster();

        foreach ($values as &$value) {
            $value = $typeCast->fromMongoType($value);
        }

        return $class::fromData($values);
    }

    /**
     * Query this collection.
     * The cursor will return Document objects.
     *
     * @param array $filter   Search filter
     * @param array $options  Options
     * @return Cursor
     */
    public function find($filter = [], array $options = [])
    {
        $cursor = $this->parent('find', $filter, $options);
        $lazy = !empty($options['projection']);

        return $this->createCursor($cursor, $lazy);
    }

    /**
     * Queries this collection, returning a single element.
     * Returns a Document object, unless you specify fields.
     *
     * @param array $filter
     * @param array $options
     * @return array|Document
     */
    public function findOne($filter = [], array $options = [])
    {
        $values = $this->parent('findOne', $filter, $options);

        if (isset($this->documentClass) && isset($values)) {
            $values = $this->asDocument($values, !empty($options['projection']));
        }

        return $values;
    }

    /**
     * Get TypeCast instance
     *
     * @codeCoverageIgnore
     * @return Jasny\DB\Mongo\TypeCast\DeepCast
     */
    protected function getTypeCaster()
    {
        return new TypeCast\DeepCast();
    }

    /**
     * Create cursor with result of 'find' method
     *
     * @codeCoverageIgnore
     * @param MongoDB\Driver\Cursor $cursor
     * @param boolean $lazy
     * @return Cursor
     */
    protected function createCursor($cursor, $lazy)
    {
        return new Cursor($cursor, $this, $lazy);
    }
}

