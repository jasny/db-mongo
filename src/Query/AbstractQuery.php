<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Query;

/**
 * Base class for query objects.
 */
abstract class AbstractQuery implements QueryInterface
{
    protected string $method;

    /** @var array<string, mixed> */
    protected array $options;

    /**
     * Query constructor.
     *
     * @param string               $method
     * @param array<string, mixed> $options
     */
    public function __construct(string $method, array $options = [])
    {
        $this->method = $method;
        $this->options = $options;
    }


    /**
     * Get the query method.
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * Get the query method if it's one of the expected methods.
     *
     * @throws \UnexpectedValueException
     */
    public function getExpectedMethod(string ...$expected): string
    {
        if (!in_array($this->method, $expected, true)) {
            throw new \UnexpectedValueException("Unexpected query method '{$this->method}', "
                . "should be one of " . join(',', $expected));
        }

        return $this->method;
    }

    /**
     * Set the query method.
     */
    public function setMethod(string $method): void
    {
        $this->method = $method;
    }


    /**
     * Get MongoDB query options.
     */
    public function getOptions(): array
    {
        return $this->options;
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
}
