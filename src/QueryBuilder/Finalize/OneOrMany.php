<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\QueryBuilder\Finalize;

use Improved\IteratorPipeline\Pipeline;
use Jasny\DB\Exception\UnsupportedFeatureException;
use Jasny\DB\Mongo\Query\QueryInterface;
use Jasny\DB\Option\LimitOption;
use Jasny\DB\Option\OptionInterface;

/**
 * Choose method based on limit.
 */
class OneOrMany
{
    /**
     * Convert a query opts to a MongoDB options.
     *
     * @param QueryInterface    $query
     * @param OptionInterface[] $opts
     */
    public function __invoke(QueryInterface $query, array $opts): void
    {
        $method = $query->getMethod();

        /** @var LimitOption|null $limit */
        $limit = Pipeline::with($opts)
            ->filter(fn($opt) => $opt instanceof LimitOption)
            ->last();

        if (isset($limit) && $limit->getLimit() !== 1) {
            $msg = "MongoDB can $method one document or all documents, but not exactly " . $limit->getLimit();
            throw new UnsupportedFeatureException($msg);
        }

        $query->setMethod($method . (isset($limit) ? 'One' : 'Many'));
    }
}
