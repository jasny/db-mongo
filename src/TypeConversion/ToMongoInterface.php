<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\TypeConversion;

/**
 * Interface for service that can convert PHP types to MongoDB types
 */
interface ToMongoInterface
{
    /**
     * Convert value to MongoDB type.
     *
     * @param mixed $value
     * @return mixed
     */
    public function __invoke($value);
}
