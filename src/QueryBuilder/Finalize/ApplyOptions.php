<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Finalize;

use Improved\IteratorPipeline\Pipeline;
use Jasny\DB\FieldMap\FieldMapInterface;
use Jasny\DB\Mongo\Query\QueryInterface;
use Jasny\DB\Option\FieldsOption;
use Jasny\DB\Option\LimitOption;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\Option\SortOption;

/**
 * Convert a query opts to a MongoDB options.
 */
class ApplyOptions
{
    protected FieldMapInterface $map;

    /**
     * OptionConverter constructor.
     */
    public function __construct(FieldMapInterface $map)
    {
        $this->map = $map;
    }

    /**
     * Convert a query opts to a MongoDB options.
     *
     * @param QueryInterface    $query
     * @param OptionInterface[] $opts
     */
    public function __invoke(QueryInterface $query, array $opts): void
    {
        Pipeline::with($opts)
            ->map(\Closure::fromCallable([$this, 'convertOpt']))
            ->flatten(true)
            ->group(fn($_, string $key) => $key)
            ->map(fn($grouped) => array_reduce($grouped, function ($carry, $item) {
                return is_array($carry) && is_array($item) ? array_merge($carry, $item) : $item;
            }, null))
            ->apply(function ($value, string $name) use ($query) {
                $query->setOption($name, $value);
            })
            ->walk();
    }


    /**
     * Convert a standard Jasny DB opt to a MongoDB option.
     *
     * @param OptionInterface $opt
     * @return array<string, mixed>
     */
    protected function convertOpt(OptionInterface $opt): array
    {
        if ($opt instanceof FieldsOption) {
            return $this->convertFields($opt->getFields(), $opt->isNegated());
        }

        if ($opt instanceof SortOption) {
            return $this->convertSort($opt->getFields());
        }

        if ($opt instanceof LimitOption) {
            return ['limit' => $opt->getLimit()] + ($opt->getOffset() !== 0 ? ['skip' => $opt->getOffset()] : []);
        }

        return [];
    }

    /**
     * Convert fields / omit opt to MongoDB projection option.
     *
     * @param string[] $fields
     * @param bool     $negate
     * @return array<string, array<string, int>>
     */
    protected function convertFields(array $fields, bool $negate = false): array
    {
        $projection = Pipeline::with($fields)
            ->typeCheck('string', new \UnexpectedValueException())
            ->flip()
            ->fill($negate ? 0 : 1)
            ->mapKeys(fn($_, string $field) => $this->map->toDB($field))
            ->toArray();

        if (!$negate && !isset($projection['_id'])) {
            $projection['_id'] = 0; // MongoDB returns `_id` when unspecified.
        }

        return ['projection' => $projection];
    }

    /**
     * Convert sort opt to MongoDB sort option.
     *
     * @param string[] $fields
     * @return array{sort:array<string, int>}
     */
    protected function convertSort(array $fields): array
    {
        $sort = Pipeline::with($fields)
            ->typeCheck('string', new \UnexpectedValueException())
            ->flip()
            ->map(fn($_, string $field) => ($field[0] === '~' ? -1 : 1))
            ->mapKeys(fn(int $asc, string $field) => ($asc < 0 ? substr($field, 1) : $field))
            ->mapKeys(fn($_, string $field) => $this->map->toDB($field))
            ->toArray();

        return ['sort' => $sort];
    }
}
