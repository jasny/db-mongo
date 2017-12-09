<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\BasicEntity,
    Jasny\DB\Entity\LazyLoading;

/**
 * Concrete implementation of abstract class BasicEntity
 */
class TestEntityLazy extends BasicEntity implements LazyLoading
{
    /**
     * @var DateTime
     **/
    public $date;

    /**
     * @var string
     **/
    public $zoo;

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
