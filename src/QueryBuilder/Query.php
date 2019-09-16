<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Improved\IteratorPipeline\Pipeline;

/**
 * Accumulator for MongoDB query builder.
 * @option
 */
class Query
{
    /**
     * @var array<string, mixed>
     */
    protected array $options;

    /**
     * @var array<string, array>
     */
    protected array $statements = [];


    /**
     * Query constructor.
     *
     * @param array<string, mixed> $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }


    /**
     * Set MongoDB specific query option.
     *
     * @param string $name
     * @param mixed $value
     */
    public function setOption(string $name, $value): void
    {
        $this->options[$name] = $value;
    }

    /**
     * Add a statement to the query.
     */
    public function add(array $statement): void
    {
        $this->statements[] = $statement;
    }


    /**
     * Get MongoDB query options.
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get all statements merged.
     */
    protected function getMergedStatements(): array
    {
        return Pipeline::with($this->statements)
            ->flatten(true)
            ->group(function ($value, string $key) {
                return $key;
            })
            ->map(function (array $value, string $key) {
                return $key[0] === '$' ? array_merge(...$value) : end($value);
            })
            ->toArray();
    }

    /**
     * Get MongoDB query statements.
     */
    public function toArray(): array
    {
        $hasOr = Pipeline::with($this->statements)->flatten(true)
            ->hasAny(function ($value, string $key) {
                return $key === '$or';
            });

        return $hasOr ? ['$and' => $this->statements] : $this->getMergedStatements();
    }
}
