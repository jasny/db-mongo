<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Improved\IteratorPipeline\Pipeline;
use Jasny\DB\Exception\InvalidOptionException;
use Jasny\DB\Option\FieldsOption;
use Jasny\DB\Option\LimitOption;
use Jasny\DB\Option\QueryOptionInterface;
use function Jasny\expect_type;

/**
 * Convert a query opts to a MongoDB options.
 */
class OptionConverter
{
    /**
     * Convert a query opts to a MongoDB options.
     *
     * @param QueryOptionInterface[] $opts
     * @return array
     * @throws InvalidOptionException
     */
    public function convert(array $opts): array
    {
        return Pipeline::with($opts)
            ->map(\Closure::fromCallable([$this, 'convertStandardOpt']))
            ->flatten(true)
            ->group(function($value, string $key) {
                return $key;
            })
            ->map(function($grouped) {
                return array_reduce($grouped, function($carry, $item) {
                    return is_array($carry) && is_array($item) ? array_merge($carry, $item) : $item;
                }, null);
            })
            ->toArray();
    }

    /**
     * Alias of `convert()`
     *
     * @param QueryOptionInterface[] $opts
     * @return array
     */
    final public function __invoke(array $opts): array
    {
        return $this->convert($opts);
    }


    /**
     * Convert a standard Jasny DB opt to a MongoDB option.
     *
     * @param QueryOptionInterface $opt
     * @return array
     * @throws InvalidOptionException
     */
    protected function convertStandardOpt(QueryOptionInterface $opt): array
    {
        if ($opt instanceof FieldsOption) {
            return $opt->getType() === 'sort'
                ? $this->convertSort($opt->getFields())
                : $this->convertFields($opt->getType(), $opt->getFields());
        }

        if ($opt instanceof LimitOption) {
            return ['limit' => $opt->getLimit()] + ($opt->getOffset() !== 0 ? ['skip' => $opt->getOffset()] : []);
        }

        throw new InvalidOptionException(sprintf("Unsupported query option class '%s'", get_class($opt)));
    }

    /**
     * Convert fields / omit opt to MongoDB projection option.
     *
     * @param string   $type    ('fields', 'omit')
     * @param string[] $fields
     * @return array
     */
    protected function convertFields(string $type, array $fields): array
    {
        if (!in_array($type, ['fields', 'omit'])) {
            throw new InvalidOptionException("Unknown query option '$type'");
        }

        $inc = $type === 'omit' ? -1 : 1;
        $projection = [];

        foreach ($fields as $field) {
            expect_type($field, 'string', \InvalidOptionException::class);

            $projection[$field] = $inc;
        }

        return ['projection' => $projection];
    }

    /**
     * Convert sort opt to MongoDB sort option.
     *
     * @param string[] $fields
     * @return array
     */
    protected function convertSort(array $fields): array
    {
        $sort = [];

        foreach ($fields as $field) {
            expect_type($field, 'string', \InvalidOptionException::class);

            $asc = $field[0] === '~' ? -1 : 1;
            $key = $asc === -1 ? substr($field, 1) : $field;
            $sort[$key] = $asc;
        }

        return ['sort' => $sort];
    }
}
