<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Query;

/**
 * Interface for query object that function as accumulator for the query builder.
 */
interface QueryInterface
{
    /**
     * Get the query method.
     */
    public function getMethod(): string;

    /**
     * Get the query method if it's one of the expected methods.
     *
     * @throws \UnexpectedValueException
     */
    public function getExpectedMethod(string ...$expected): string;

    /**
     * Set the query method.
     */
    public function setMethod(string $method): void;


    /**
     * Get MongoDB query options.
     */
    public function getOptions(): array;

    /**
     * Set MongoDB specific query option.
     *
     * @param string $name
     * @param mixed $value
     */
    public function setOption(string $name, $value): void;
}
