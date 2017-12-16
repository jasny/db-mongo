<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\Entity\LazyLoading,
    Jasny\DB\Mongo\Document\BasicImplementation;

/**
 * Stub for lazy document instance
 */
class TestDocumentLazy implements LazyLoading
{
    use BasicImplementation;

    /**
     * @var string
     **/
    public $foo;

    /**
     * Create a ghost object.
     *
     * @param mixed|array $values  Unique ID or values
     * @return Entity\Ghost
     */
    public static function lazyload($values)
    {

    }

    /**
     * Check if the object is a ghost.
     *
     * @return boolean
     */
    public function isGhost()
    {

    }

    /**
     * Expand a ghost.
     * Does nothing is entity isn't a ghost.
     *
     * @return $this
     */
    public function expand()
    {

    }
}
