<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Improved\IteratorPipeline\Pipeline;
use Jasny\DB\Option\QueryOption;

/**
 * Stage query builder, build step for MongoDB
 */
class BuildStep
{
    /**
     * @var OptionConverter
     */
    protected $optionConverter;

    /**
     * BuildStep constructor.
     *
     * @param OptionConverter $optionConverter
     */
    public function __construct(OptionConverter $optionConverter)
    {
        $this->optionConverter = $optionConverter;
    }

    /**
     * Invoke the build step.
     *
     * @param iterable               $callbacks
     * @param QueryOption[] $opts
     * @return Query
     */
    public function __invoke(iterable $callbacks, array $opts): Query
    {
        $query = new Query($this->optionConverter->convert($opts));

        Pipeline::with($callbacks)
            ->apply(function (callable $callback, array $info) use ($query, $opts) {
                $callback($query, $info['field'], $info['operator'] ?? '', $info['value'], $opts);
            })
            ->walk();

        return $query;
    }
}
