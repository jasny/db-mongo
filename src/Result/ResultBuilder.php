<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Result;

use Improved\IteratorPipeline\PipelineBuilder;
use Jasny\DB\FieldMap\ConfiguredFieldMap;
use Jasny\DB\Mongo\TypeConversion\CastToPHP;
use Jasny\DB\Result;

/**
 * Pipeline builder for a query result.
 * @immutable
 */
class ResultBuilder extends PipelineBuilder
{
    /**
     * ResultBuilder constructor.
     */
    public function __construct()
    {
        $configured = $this
            ->map(new ConfiguredFieldMap(['_id' => 'id']))
            ->then(new CastToPHP())
            ->then(fn(iterable $iterable) => new Result($iterable));

        $this->steps = $configured->steps;
    }
}
