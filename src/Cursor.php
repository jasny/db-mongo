<?php

namespace Jasny\DB\Mongo;

/**
 * MongoDB cursor that produces Records
 *
 * @author  Arnold Daniels <arnold@jasny.net>
 * @license https://raw.github.com/jasny/db-mongo/master/LICENSE MIT
 * @link    https://jasny.github.io/db-mongo
 */
class Cursor implements \IteratorAggregate
{
    /**
     * Php mongo driver cursor
     * @var MongoDB\Driver\Cursor
     */
    protected $cursor;

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
     * @param \MongoDB\Driver\Cursor  $cursor
     * @param Collection              $collection
     * @param boolean                 $lazy
     */
    public function __construct(\MongoDB\Driver\Cursor $cursor, Collection $collection, $lazy)
    {
        $this->cursor = $cursor;
        $this->collection = $collection;
        $this->lazy = !!$lazy;
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
     * Get iterator, that can cast fetched records to collection class
     * @return generator
     */
    public function getIterator()
    {
        $documentClass = isset($this->collection) ? $this->collection->getDocumentClass() : null;
        $self = $this;

        $generator = function () use ($documentClass, $self) {
            foreach ($self->cursor as $key => $values) {
                if ($values && $documentClass) {
                    $values = $self->collection->asDocument($values, $this->lazy);
                }

                yield $key => $values;
            }
        };

        return $generator();
    }

    /**
     * Call methods of driver cursor
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public function __call($method, $args)
    {
        if (method_exists($this->cursor, $method)) {
            return call_user_func_array([$this->cursor, $method], $args);
        }

        throw new \BadMethodCallException("Method '$method' does not exists");
    }
}
