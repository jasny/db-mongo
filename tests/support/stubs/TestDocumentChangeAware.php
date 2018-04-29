<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Mongo\Document\BasicImplementation,
    Jasny\DB\Mongo\Document\SoftDeletion,
    Jasny\DB\Entity\ChangeAware,
    Jasny\DB\Entity\ChangeAware\Implementation as ChangeAwareImplementation,
    Jasny\DB\Entity\Identifiable;

/**
 * Stub for document, implementing ChangeAware
 */
class TestDocumentChangeAware implements ChangeAware
{
    use BasicImplementation,
        ChangeAwareImplementation;

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
