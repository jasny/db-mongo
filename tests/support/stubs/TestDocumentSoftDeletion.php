<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Mongo\Document\BasicImplementation,
    Jasny\DB\Mongo\Document\SoftDeletion,
    Jasny\DB\Entity\Identifiable;

/**
 * Stub for document with soft deletion
 */
class TestDocumentSoftDeletion implements SoftDeletion, Identifiable
{
    use BasicImplementation,
        SoftDeletion\FlagImplementation
    {
        SoftDeletion\FlagImplementation::filterToQuery insteadof BasicImplementation;
        SoftDeletion\FlagImplementation::delete insteadof BasicImplementation;
    }

    /**
     * Mock for collection
     * @var \Jasny\DB\Mongo\Collection
     **/
    public static $collectionMock;

    /**
     * @var string
     **/
    public $id;

    /**
     * Get the Mongo collection mock
     *
     * @return \Jasny\DB\Mongo\Collection
     */
    protected static function getCollection()
    {
        return static::$collectionMock;
    }
}
