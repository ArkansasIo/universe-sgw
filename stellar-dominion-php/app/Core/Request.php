<?php

declare(strict_types=1);

namespace StellarDominion\Core;

/**
 * Lightweight HTTP request wrapper (PHP superglobals + JSON body).
 */
final class Request
{
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly array $query,
        private readonly array $body,
        private readonly array $headers,
        private readonly string $ip,
    ) {}

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        $body = [];
        $raw = file_get_contents('php://input');
        if ($raw !== '' && $raw !== false) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $body = $decoded;
            } else {
                parse_str($raw, $body);
            }
        }

        return new self(
            $method,
            $path,
            $_GET,
            array_replace_recursive($body, $_POST),
            $headers,
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        );
    }

    public function method(): string { return $this->method; }
    public function path(): string { return $this->path; }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    public function all(): array { return $this->body; }
    public function header(string $name, mixed $default = null): mixed
    {
        return $this->headers[strtolower($name)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if (is_string($auth) && preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            return $m[1];
        }
        return null;
    }

    public function ip(): string { return $this->ip; }
}
