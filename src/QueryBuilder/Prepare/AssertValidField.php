<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Prepare;

/**
 * Assert that the field name valid in MongoDB.
 */
class AssertValidField
{
    /**
     * Called by query builder.
     */
    public function __invoke(iterable $items): \Generator
    {
        foreach ($items as $info => $value) {
            $field = is_array($info) ? ($info['field'] ?? '') : $info;

            if ($field === '') {
                throw new \UnexpectedValueException("Field is an empty string or isn't set");
            }

            if ($field[0] === '$' || strpos($field, '.$') !== false) {
                throw new \UnexpectedValueException("Invalid field '$field': Starting with '$' isn't allowed");
            }

            yield $info => $value;
        }
    }
}
