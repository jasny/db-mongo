<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Improved\IteratorPipeline\Pipeline;

/**
 * Accumulator for MongoDB query builder.
 */
class Query
{
    /**
     * @var array<string, mixed>
     */
    protected $options;

    /**
     * @var array<string, array>
     */
    protected $statements = [];


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
     * Set MongoDB specific option
     *
     * @param string $name
     * @param mixed $value
     */
    public function setOption(string $name, $value)
    {
        $this->options[$name] = $value;
    }

    /**
     * Add a statement to the query.
     *
     * @param array $statement
     * @return void
     */
    public function add(array $statement): void
    {
        $this->statements[] = $statement;
    }


    /**
     * Get MongoDB query options.
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get all statements merged.
     *
     * @return array
     */
    protected function getMergedStatements()
    {
        return Pipeline::with($this->statements)
            ->flatten(true)
            ->group(function($value, string $key) {
                return $key;
            })
            ->map(function(array $value, string $key) {
                return $key[0] === '$' ? array_merge(...$value) : end($value);
            })
            ->toArray();
    }

    /**
     * Get MongoDB query statements.
     *
     * @return array
     */
    public function toArray(): array
    {
        $hasOr = Pipeline::with($this->statements)->flatten(true)->hasAny(function($value, string $key) {
            return $key === '$or';
        });

        return $hasOr ? ['$and' => $this->statements] : $this->getMergedStatements();
    }
}
