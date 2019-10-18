<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Prepare;

use MongoDB\BSON;

/**
 * Assert that the data doesn't contains MongoDB operators.
 */
class AssertValidValue
{
    /**
     * Called by query builder.
     */
    public function __invoke(iterable $items): \Generator
    {
        foreach ($items as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $this->assertNoMongoOperator($value, $key);
            }

            yield $key => $value;
        }
    }

    /**
     * Ensure the value has no keys that are mongo operators (recursively).
     *
     * @param iterable|object $element
     * @param mixed           $top
     * @param string[]        $parents
     * @throws \UnexpectedValueException
     */
    protected function assertNoMongoOperator($element, $top, array $parents = []): void
    {
        if (count($parents) >= 32) {
            throw new \OverflowException("Possible circular reference");
        }

        foreach ($element as $key => $value) {
            if (is_string($key) && ($key[0] === '$' || strpos($key, '.$') !== false)) {
                $desc = $this->describe($key, $parents, $top);
                throw new \UnexpectedValueException("Illegal property {$desc}: starting with '$' isn't allowed");
            }

            if (is_array($value) || (is_object($value) && !$value instanceof BSON\Type)) {
                $this->assertNoMongoOperator($value, $top, array_merge($parents, [$key])); // recursion
            }
        }
    }

    /**
     * Describe the path of the key.
     *
     * @param string   $key
     * @param string[] $parents
     * @param mixed    $top
     * @return string
     */
    protected function describe(string $key, array $parents, $top): string
    {
        if (is_string($top)) {
            $desc = $top;
        } elseif (is_array($top) && (isset($top['field']) || isset($top['operator']))) {
            $desc = ($top['field'] ?? '') . (isset($top['operator']) ? '(' . $top['operator'] . ')' : '');
        } else {
            $desc = '';
        }

        if ($parents !== []) {
            $desc .= ($desc !== '' ? ':' : '') . join('.', $parents);
        }

        return "'{$key}'" . ($desc !== '' ? " in '$desc'" : '');
    }
}
