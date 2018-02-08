<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\BasicEntity,
    Jasny\DB\Entity\LazyLoading,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\Mongo\Document\LazyLoading\Implementation as LazyLoadingImplementation;

/**
 * Concrete implementation of abstract class BasicEntity with lazy loading
 */
class TestEntityLazyComplexId extends BasicEntity implements LazyLoading, Identifiable
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
     * Get complex id
     *
     * @return array
     */
    public static function getIdProperty()
    {
        return ['foo', 'bar'];
    }

    /**
     * Get entity id.
     *
     * @return mixed
     */
    public function getId()
    {

    }

    /**
     * Reload the entity
     *
     * @param array $opts
     */
    public function reload(array $opts = [])
    {

    }
}
