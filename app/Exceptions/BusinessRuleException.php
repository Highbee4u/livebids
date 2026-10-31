<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class BusinessRuleException extends Exception
{
    public function __construct(
        string $message,
        protected string $errorCode = 'BUSINESS_RULE_VIOLATION',
        protected int $statusCode = 422,
        protected ?array $errors = null
    ) {
        parent::__construct($message, $statusCode);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }
}
