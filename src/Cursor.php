<?php

namespace Jasny\DB\Mongo;

/**
 * MongoDB cursor that produces Records
 *
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
class Cursor extends \MongoCursor
{
    /**
     * Record class
     * @var Collection
     */
    protected $collection;

    /**
     * Is lazy load
     * @var boolean
     */
    protected $lazy = false;

    /**
     * Class constructor
     *
     * @codeCoverageIgnore
     * @param \MongoClient      $connection
     * @param Collection|string $ns
     * @param array             $query
     * @param array             $fields
     */
    public function __construct(\MongoClient $connection, $ns, array $query = [], array $fields = [])
    {
        if ($ns instanceof Collection) $this->collection = $ns;
        $this->lazy = !empty($fields);

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
     * @codeCoverageIgnore
     * @return array|object
     */
    public function current()
    {
        $values = parent::current();

        if (isset($values) && isset($this->collection) && $this->collection->getDocumentClass()) {
            $values = $this->collection->asDocument($values, $this->lazy);
        }

        return $values;
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
