<?php

namespace Jasny\DB\Mongo;

use Jasny\DB\BasicEntity,
    Jasny\DB\Entity\LazyLoading,
    Jasny\DB\Entity\Identifiable,
    Jasny\DB\Mongo\Document\LazyLoading\Implementation as LazyLoadingImplementation;

/**
 * Concrete implementation of abstract class BasicEntity with lazy loading
 */
class TestEntityLazySimpleId extends BasicEntity implements LazyLoading, Identifiable
{
    use LazyLoadingImplementation;

    /**
     * @var string
     **/
    public $id;

    /**
     * @var DateTime
     **/
    public $date;

    /**
     * @var string
     **/
    public $zoo;

    /**
     * Get entity id.
     *
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get complex id
     *
     * @return array
     */
    public static function getIdProperty()
    {
        return 'id';
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
