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
    protected $source;

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
     * @param \MongoDB\Driver\Cursor  $source
     * @param Collection              $collection
     * @param boolean                 $lazy
     */
    public function __construct(\MongoDB\Driver\Cursor $source, Collection $collection, $lazy)
    {
        $this->source = $source;
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
     * @return Iterator
     */
    public function getIterator()
    {
        $documentClass = isset($this->collection) ? $this->collection->getDocumentClass() : null;
        $self = $this;

        $generator = function () use ($documentClass, $self) {
            $source = $self->source ?: [];

            foreach ($source as $key => $values) {
                if (isset($values) && $documentClass) {
                    $values = $self->collection->asDocument($values, $self->lazy);
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
        if (method_exists($this->source, $method)) {
            return call_user_func_array([$this->source, $method], $args);
        }

        throw new \BadMethodCallException("Method '$method' does not exists");
    }
}
