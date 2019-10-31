<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Traits;

use Jasny\Immutable;
use MongoDB\Collection;

/**
 * Link service to MongoDB collection.
 */
trait CollectionTrait
{
    use Immutable\With;

    protected Collection $collection;

    /**
     * Create a copy of this service, linked to the MongoDB collection.
     *
     * @param Collection $collection
     * @return static
     */
    public function forCollection(Collection $collection): self
    {
        return $this->withProperty('collection', $collection);
    }

    /**
     * Get the mongodb collection the associated with the service.
     */
    public function getCollection(): Collection
    {
        if (!isset($this->collection)) {
            throw new \LogicException("This is a template service. "
                . "Create a copy that's linked to a MongoDB Collection with `forCollection()`");
        }

        return $this->collection;
    }

    /**
     * Alias of `getCollection()`.
     */
    final public function getStorage(): Collection
    {
        return $this->getCollection();
    }
}
