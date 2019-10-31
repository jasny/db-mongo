<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Query;

use Improved\IteratorPipeline\Pipeline;

/**
 * Representation of a MongoDB bulk write query.
 * This object is mutable, as it's uses as accumulator by the query builders.
 *
 * @see https://docs.mongodb.com/php-library/v1.2/reference/method/MongoDBCollection-bulkWrite/
 */
class WriteQuery implements QueryInterface
{
    protected const SUPPORTED_OPERATIONS = [
        'deleteMany',
        'deleteOne',
        'insertOne',
        'replaceOne',
        'updateMany',
        'updateOne',
    ];

    /** @var array */
    protected array $index = [];

    /** @var array<int,array> */
    protected array $operations = [];

    /** @var array<string, mixed> */
    protected array $options;


    /**
     * WriteQuery constructor.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }


    /**
     * Get all operations of the bulk write.
     *
     * @return array<int,array>
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    /**
     * Get the keys of the operations.
     *
     * @return array
     */
    public function getIndex(): array
    {
        return $this->index;
    }


    /**
     * Loop through all operations, replacing them with the return value of the callback
     *
     * @param callable $callable
     */
    public function map(callable $callable): void
    {
        foreach ($this->operations as &$operation) {
            $operation = $callable($operation);
        }
    }

    /**
     * Add a bulk write operation. Arguments depend on the operation;
     *
     *   add('deleteMany', $filter)
     *   add('deleteOne',  $filter)
     *   add('insertOne',  $document)
     *   add('replaceOne', $filter, $replacement, $options)
     *   add('updateMany', $filter, $update, $options)
     *   add('updateOne',  $filter, $update, $options)
     *
     * @param string $operation
     * @param mixed  ...$args
     */
    final public function add(string $operation, ...$args): void
    {
        $this->addIndexed(null, $operation, ...$args);
    }

    /**
     * Add an operation with a key.
     *
     * @param mixed  $key
     * @param string $operation
     * @param mixed  ...$args
     */
    public function addIndexed($key, string $operation, ...$args): void
    {
        if (!in_array($operation, static::SUPPORTED_OPERATIONS, true)) {
            throw new \InvalidArgumentException("Unsupported bulk write operation '$operation'");
        }

        $this->operations[] = [$operation => $args];
        $this->index[] = $key ?? array_key_last($this->operations);
    }


    /**
     * Get the write operation.
     */
    public function getMethod(): string
    {
        return count($this->operations) === 1 ? (string)key($this->operations[0]) : 'bulkWrite';
    }

    /**
     * It's not possible to explicitly set the save query method.
     *
     * @throws \LogicException
     */
    public function setMethod(string $method): void
    {
        throw new \LogicException("Unable to set method to '$method'. "
            . "The save query method is determined based on the operations.");
    }

    /**
     * Get the query method if it's one of the expected methods.
     *
     * @throws \LogicException
     */
    public function getExpectedMethod(string ...$expected): string
    {
        throw new \LogicException("Use WriteQuery::expectedMethods() instead.");
    }

    /**
     * Expect operations to be one of the given types.
     *
     * @throws \UnexpectedValueException
     */
    public function expectMethods(string ...$expected): void
    {
        $methods = Pipeline::with($this->operations)
            ->map(fn(array $operation) => key($operation))
            ->unique()
            ->toArray();

        $unexpected = array_diff($methods, $expected);

        if (count($unexpected) > 0) {
            throw new \UnexpectedValueException("Unexpected write operations '" . join("', '", $unexpected) . "', "
                . "should be one of '" . join("', '", $expected) . "'");
        }
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
