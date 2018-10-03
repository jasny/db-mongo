<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Jasny\DB\Mongo\Exception\InvalidFilterException;
use Jasny\DB\Mongo\TypeConversion\ToMongo as TypeConversion;
use Jasny\DB\Mongo\TypeConversion\ToMongoInterface as TypeConversionInterface;
use Jasny\IteratorPipeline\Pipeline;
use Jasny\IteratorPipeline\PipelineBuilder;

/**
 * Interface for service that can convert a filter to a MongoDB query.
 * @immutable
 */
class QueryBuilder implements QueryBuilderInterface
{
    /**
     * Default operator conversion
     */
    protected const OPERATORS = [
        '' => null,
        'not' => '$ne',
        'min' => '$gte',
        'max' => '$lte',
        'any' => '$in',
        'none' => '$nin',
        'all' => '$all'
    ];

    /**
     * @var callable[]
     */
    protected $customFilters = [];

    /**
     * @var TypeConversionInterface
     */
    protected $typeConversion;

    /**
     * @var PipelineBuilder
     */
    protected $pipeline;


    /**
     * QueryBuilder constructor.
     *
     * @param TypeConversionInterface $typeConversion
     */
    public function __construct(TypeConversionInterface $typeConversion = null)
    {
        $this->typeConversion = $typeConversion ?? new TypeConversion();

        $this->customFilters['id'] = $this->alias('_id');
        $this->customFilters[':id'] = $this->customFilters['id'];
    }

    /**
     * Create the pipeline for building a query.
     * The end of the pipeline is a set of Closures which take one argument; a Query.
     *
     * @return PipelineBuilder
     */
    protected function createPipeline()
    {
        return Pipeline::build()
            ->mapKeys(function(string $key): array {
                return preg_match('/^\s*(?<field>[^\s(]+)\s*(?:\((?<operator>[^)]+)\)\s*)?$/', $key, $matches)
                    ? ($matches + ['operator' => null])
                    : ['field' => trim($key), 'operator' => null];
            })
            ->mapKeys(function(array $key): array {
                return isset($this->aliases[$key['field']]) ? ['field' => $this->aliases[$key['field']]] + $key : $key;
            })
            ->map($this->typeConversion)
            ->map(function($value, array $key): \Closure {
                $fn = $this->customFilters[$key['field']] ?? [$this, 'applyDefault'];

                return function(Query $query) use ($fn, $key, $value): void {
                    $fn($query, $key['field'], $key['operator'], $value, $this);
                };
            });
    }

    /**
     * Get the pipeline for building a query
     *
     * @return PipelineBuilder
     */
    protected function getPipeline()
    {
        if (!isset($this->pipeline)) {
            $this->pipeline = $this->createPipeline();
        }

        return $this->pipeline;
    }


    /**
     * Create a closure for applying an alias.
     *
     * @param string $field
     * @return \Closure
     */
    public function alias(string $field): \Closure
    {
        return function(Query $query, string $alias, string $operator, $value) use ($field): void {
            $fn = $this->customFilters[$field] ?? [$this, 'applyDefault'];

            return $fn($query, $field, $operator, $value, $this);
        };
    }

    /**
     * Create a query builder with a custom filter criteria.
     *
     * @param string $key
     * @param callable $apply
     * @return static
     */
    public function with(string $key, callable $apply)
    {
        $clone = clone $this;
        $clone->customFilters[$key] = $apply;

        return $clone;
    }


    /**
     * Default logic to apply a filter criteria.
     *
     * @param Query  $query
     * @param string $field
     * @param string $operator
     * @param mixed  $value
     * @return void
     * @throws InvalidFilterException
     */
    protected function applyDefault(Query $query, string $field, string $operator, $value): void
    {
        if ($field[0] === '$') {
            throw new InvalidFilterException("Invalid filter key '$field'. Starting with '$' isn't allowed.");
        }

        if (!array_key_exists($operator, self::OPERATORS)) {
            throw new InvalidFilterException("Invalid filter key '$field'. Unknown operator '$operator'.");
        }

        $mongoOperator = self::OPERATORS[$operator];
        $condition = isset($mongoOperator) ? [$field => [$mongoOperator => $value]] : [$field => $value];

        $query->add($condition);
    }

    /**
     * Convert a a filter into a query.
     *
     * @param iterable $filter
     * @return array
     */
    public function buildQuery(iterable $filter): array
    {
        $query = new Query();

        $this->getPipeline()
            ->with($filter)
            ->apply(function($fn) use ($query): void {
                $fn($query);
            })
            ->walk();

        return $query->toArray();
    }
}
