<?php

declare(strict_types=1);

namespace StellarDominion\Core;

/**
 * JSON HTTP response helper (mirrors Express res.json).
 */
final class Response
{
    private int $status = 200;
    private array $headers = [];

    public static function make(int $status = 200): self
    {
        $instance = new self();
        return $instance->status($status);
    }

    public function status(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function json(mixed $data, int $status = null): never
    {
        http_response_code($status ?? $this->status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function success(mixed $data = [], string $message = 'OK', int $status = 200): never
    {
        $this->json(['status' => 'success', 'message' => $message, 'data' => $data], $status);
    }

    public function error(string $message, int $status = 500, array $details = []): never
    {
        $payload = ['status' => 'error', 'message' => $message];
        if ($details !== []) {
            $payload['details'] = $details;
        }
        $this->json($payload, $status);
    }

    public function notFound(string $message = 'Not found'): never
    {
        $this->error($message, 404);
    }

    public static function errorResponse(\Throwable $e): never
    {
        Logger::error('Unhandled exception', ['exception' => $e->getMessage()]);
        (new self())->error('Internal server error', 500);
    }
}
