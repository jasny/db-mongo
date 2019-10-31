<?php

declare(strict_types=1);

namespace Jasny\DB\Mongo\Traits;

use Jasny\Immutable;
use MongoDB\Collection;
use Psr\Log\LoggerInterface;

/**
 * Logging (debug) for read and write service.
 */
trait LoggingTrait
{
    use Immutable\With;

    protected ?LoggerInterface $logger;

    /**
     * Get the mongodb collection the associated with the service.
     */
    abstract public function getCollection(): Collection;

    /**
     * Enable (debug) logging.
     *
     * @return static
     */
    public function withLogging(LoggerInterface $logger): self
    {
        return $this->withProperty('logger', $logger);
    }

    /**
     * Log a debug message.
     */
    protected function debug(string $message, array $context): void
    {
        if (isset($this->logger)) {
            $this->logger->debug(sprintf($message, $this->getCollection()->getCollectionName()), $context);
        }
    }
}
