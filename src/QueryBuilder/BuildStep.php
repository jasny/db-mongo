<?php declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder;

use Jasny\DB\Option\QueryOptionInterface;

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
     * @param QueryOptionInterface[] $opts
     * @return Query
     */
    public function __invoke(iterable $callbacks, array $opts): Query
    {
        $query = new Query($this->optionConverter->convert($opts));

        foreach ($callbacks as $info => $callback) {
            $callback($query, $info['field'], $info['operator'], $info['value'], $opts);
        }

        return $query;
    }
}
