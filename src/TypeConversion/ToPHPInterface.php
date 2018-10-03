<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\TypeConversion;

/**
 * Interface for service that can convert MongoDB types to PHP types
 */
interface ToPHPInterface
{
    /**
     * Convert MongoDB type to PHP value.
     *
     * @param mixed $value
     * @return mixed
     */
    public function __invoke($value);
}
