<?php

declare(strict_types=1);

namespace StellarDominion\Core;

/**
 * HTTP error exception carrying a status code. Thrown by controllers and
 * middleware to produce a JSON error response.
 */
class HttpException extends \RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message,
        array $details = []
    ) {
        parent::__construct($message);
        $this->details = $details;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public static function badRequest(string $message = 'Bad request', array $details = []): self
    {
        return new self(400, $message, $details);
    }

    public static function unauthorized(string $message = 'Unauthorized'): self
    {
        return new self(401, $message);
    }

    public static function forbidden(string $message = 'Forbidden'): self
    {
        return new self(403, $message);
    }

    public static function notFound(string $message = 'Not found'): self
    {
        return new self(404, $message);
    }

    public static function conflict(string $message = 'Conflict'): self
    {
        return new self(409, $message);
    }

    public static function unprocessable(string $message = 'Unprocessable entity', array $details = []): self
    {
        return new self(422, $message, $details);
    }

    private array $details = [];
}
