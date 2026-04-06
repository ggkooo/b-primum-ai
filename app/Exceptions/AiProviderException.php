<?php

namespace App\Exceptions;

use RuntimeException;

class AiProviderException extends RuntimeException
{
    public function __construct(string $message, private readonly int $httpStatus = 502)
    {
        parent::__construct($message);
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
