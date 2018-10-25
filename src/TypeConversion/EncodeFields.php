<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\TypeConversion;

use function Jasny\expect_type;
use function Jasny\is_numeric_array;

/**
 * Encode special characters in field name.
 */
class EncodeFields
{
    /**
     * Recursively encode document or array.
     *
     * @param array|object $item
     * @param int          $depth
     * @return array|object
     */
    protected function encode($item, int $depth = 0)
    {
        if ($depth >= 100) {
            throw new InvalidArgumentException("Unable to encode field names; possible circular reference");
        }

        return is_numeric_array($item)
            ? $this->encodeArray($item, $depth)
            : $this->encodeDocument($item, $depth);
    }

    /**
     * Encode numeric array
     *
     * @param array $array
     * @param int   $depth
     * @return array
     */
    protected function encodeArray(array $array, int $depth): array
    {
        foreach ($array as &$value) {
            $value = $this->encode($value, $depth + 1); // recursion
        }

        return $array;
    }

    /**
     * Encode document
     *
     * @param array|object $document
     * @param int          $depth
     * @return array|object
     */
    protected function encodeDocument($document, int $depth)
    {
        $copy = [];

        foreach ($document as $field => $value) {
            if (is_string($field)) {
                $field = strtr($field, ['\\' => '\\\\', '$' => '\\u0024', '.' => '\\u002e']);
            }

            if (is_object($value) || is_array($value)) {
                $value = $this->encode($value,$depth + 1); // recursion
            }

            $copy[$field] = $value;
        }

        return is_object($document) && !$document instanceof \Traversable ? (object)$copy : $copy;
    }

    /**
     * Encode document or set of documents.
     *
     * @param array|object $document
     * @return array|object
     */
    public function __invoke($document)
    {
        expect_type($document, ['array', 'object']);

        return $this->encodeDocument($document);
    }
}
