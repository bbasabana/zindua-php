<?php

declare(strict_types=1);

namespace Zindua\Exceptions;

use Exception;
use Throwable;

/** Structured error — never includes the API key in the message. */
class ZinduaException extends Exception
{
    public readonly int $httpStatus;
    public readonly string $errorCode;
    /** @var array<string, mixed>|null */
    public readonly ?array $details;

    /**
     * @param array<string, mixed>|null $details
     */
    public function __construct(
        string $message,
        int $httpStatus = 0,
        string $errorCode = 'API_ERROR',
        ?array $details = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
        $this->httpStatus = $httpStatus;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }
}
