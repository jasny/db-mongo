<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Improved\IteratorPipeline\Pipeline;
use Jasny\DB\Exception\InvalidOptionException;
use Jasny\DB\Option\FieldsOption;
use Jasny\DB\Option\LimitOption;
use Jasny\DB\Option\OptionInterface;
use Jasny\DB\Option\SortOption;

/**
 * Convert a query opts to a MongoDB options.
 */
class OptionConverter
{
    /**
     * Convert a query opts to a MongoDB options.
     *
     * @param OptionInterface[] $opts
     * @return array<string, mixed>
     * @throws InvalidOptionException
     */
    public function convert(array $opts): array
    {
        return Pipeline::with($opts)
            ->map(\Closure::fromCallable([$this, 'convertOpt']))
            ->flatten(true)
            ->group(fn($_, string $key) => $key)
            ->map(function ($grouped) {
                return array_reduce($grouped, function ($carry, $item) {
                    return is_array($carry) && is_array($item) ? array_merge($carry, $item) : $item;
                }, null);
            })
            ->toArray();
    }

    /**
     * Alias of `convert()`
     *
     * @param OptionInterface[] $opts
     * @return array<string, mixed>
     */
    final public function __invoke(array $opts): array
    {
        return $this->convert($opts);
    }


    /**
     * Convert a standard Jasny DB opt to a MongoDB option.
     *
     * @param OptionInterface $opt
     * @return array<string, int>
     * @throws InvalidOptionException
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

        throw new InvalidOptionException(sprintf("Unsupported query option class '%s'", get_class($opt)));
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
            ->typeCheck('string', new InvalidOptionException())
            ->flip()
            ->fill($negate ? 0 : 1)
            ->toArray();

        return ['projection' => $projection];
    }

    /**
     * Convert sort opt to MongoDB sort option.
     *
     * @param string[] $fields
     * @return array{sort => array<string, int>}
     */
    protected function convertSort(array $fields): array
    {
        $sort = Pipeline::with($fields)
            ->typeCheck('string', new InvalidOptionException())
            ->flip()
            ->map(fn($_, string $field) => ($field[0] === '~' ? -1 : 1))
            ->mapKeys(fn(int $asc, string $field) => ($asc < 0 ? substr($field, 1) : $field))
            ->toArray();

        return ['sort' => $sort];
    }
}
