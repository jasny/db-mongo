<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\CRUD;

use Jasny\DB\Mongo\TypeMapper\TypeMapperInterface;

/**
 * MongoDB CRUD operations on a collection.
 *
 * @immutable Follows the prototype design pattern
 */
class CollectionCRUD
{
    /**
     * CollectionCRUD constructor.
     * @param TypeMapperInterface $mapper
     */
    public function __construct(TypeMapperInterface $mapper)
    {
    }

    public function forCollection()
    {

    }
}
