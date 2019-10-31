<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo;

/**
 * Is value an associative array.
 *
 * @param mixed $value
 * @return bool
 */
function is_assoc($value): bool
{
    return is_array($value) && array_keys($value) !== array_keys(array_keys($value));
}

/**
 * Flatten all fields of an element.
 *
 * @param mixed  $element
 * @param string $path
 * @param array  $accumulator  Don't use
 * @return array
 */
function flatten_fields($element, string $path = '', array &$accumulator = []): array
{
    if (!is_array($element) && !is_object($element)) {
        $accumulator[$path] = $element;
    } else {
        foreach ($element as $key => $value) {
            i\type_check($key, 'string', new \UnexpectedValueException());

            $field = ($path === '' ? $key : "$path.$key");
            flatten_fields($value, $field, $accumulator); // recursion
        }
    }

    return $accumulator;
}
