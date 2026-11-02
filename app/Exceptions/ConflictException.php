<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Raised when a request is valid but conflicts with the current state of a resource
 * (e.g. deleting a currency that is still in use). Rendered as HTTP 409.
 */
class ConflictException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(409, $message);
    }
}
