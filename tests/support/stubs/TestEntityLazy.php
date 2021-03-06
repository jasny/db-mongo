<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\BasicEntity,
    Jasny\DB\Entity\LazyLoading,
    Jasny\DB\Mongo\Document\LazyLoading\Implementation as LazyLoadingImplementation;

/**
 * Concrete implementation of abstract class BasicEntity with lazy loading
 */
class TestEntityLazy extends BasicEntity implements LazyLoading
{
    use LazyLoadingImplementation;

    /**
     * @var DateTime
     **/
    public $date;

    /**
     * @var string
     **/
    public $zoo;

    /**
     * Reload the entity
     *
     * @param array $opts
     */
    public function reload(array $opts = [])
    {

    }
}
