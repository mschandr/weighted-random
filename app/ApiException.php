<?php
declare(strict_types=1);

namespace mschandr\WeightedRandom\Api;

/**
 * ApiException
 *
 * Represents a client-facing error with an associated HTTP status code.
 * The message is safe to return to the caller.
 */
final class ApiException extends \RuntimeException
{
    public function __construct(string $message, private readonly int $statusCode = 400)
    {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
