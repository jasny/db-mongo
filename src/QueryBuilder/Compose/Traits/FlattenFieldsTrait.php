<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Compose\Traits;

use Improved as i;

/**
 * Composer trait to flatten structures to field name paths (separated with dots).
 */
trait FlattenFieldsTrait
{
    /**
     * Flatten all fields of an element.
     *
     * @param mixed  $element
     * @param string $path
     * @param array  $accumulator  Don't use
     * @return array
     */
    protected function flattenFields($element, string $path = '', array &$accumulator = []): array
    {
        if (!is_array($element) && !is_object($element)) {
            $accumulator[$path] = $element;
        } else {
            foreach ($element as $key => $value) {
                i\type_check($key, 'string', new \UnexpectedValueException());

                $field = ($path === '' ? $key : "$path.$key");
                $this->flattenFields($value, $field, $accumulator); // recursion
            }
        }

        return $accumulator;
    }
}
