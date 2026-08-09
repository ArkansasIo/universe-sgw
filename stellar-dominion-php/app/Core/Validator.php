<?php

declare(strict_types=1);

namespace StellarDominion\Core;

/**
 * Minimal input validation helpers (port of the Zod validation layer).
 */
final class Validator
{
    /** @var array<string, array{rule:string, params?:mixed, message?:string}> */
    private array $rules = [];
    private array $errors = [];

    public static function make(array $data): self
    {
        $instance = new self();
        $instance->data = $data;
        return $instance;
    }

    public function rule(string $field, string $rule, mixed $params = null, ?string $message = null): self
    {
        $this->rules[$field][] = ['rule' => $rule, 'params' => $params, 'message' => $message];
        return $this;
    }

    public function required(string $field, ?string $message = null): self { return $this->rule($field, 'required', null, $message); }
    public function string(string $field, int $min = 1, int $max = 255, ?string $message = null): self { return $this->rule($field, 'string', ['min' => $min, 'max' => $max], $message); }
    public function int(string $field, ?int $min = null, ?int $max = null, ?string $message = null): self { return $this->rule($field, 'int', ['min' => $min, 'max' => $max], $message); }
    public function email(string $field, ?string $message = null): self { return $this->rule($field, 'email', null, $message); }
    public function in(string $field, array $allowed, ?string $message = null): self { return $this->rule($field, 'in', $allowed, $message); }

    private array $data = [];

    public function passes(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            $value = $this->data[$field] ?? null;
            foreach ($rules as $rule) {
                $ok = $this->check($field, $value, $rule);
                if (!$ok) {
                    $this->errors[$field][] = $rule['message']
                        ?? sprintf('%s is invalid', $field);
                    break;
                }
            }
        }

        return $this->errors === [];
    }

    public function errors(): array { return $this->errors; }

    private function check(string $field, mixed $value, array $rule): bool
    {
        return match ($rule['rule']) {
            'required' => $value !== null && $value !== '',
            'string' => is_string($value)
                && strlen($value) >= $rule['params']['min']
                && strlen($value) <= $rule['params']['max'],
            'int' => is_numeric($value)
                && ($rule['params']['min'] === null || $value >= $rule['params']['min'])
                && ($rule['params']['max'] === null || $value <= $rule['params']['max']),
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'in' => in_array($value, $rule['params'], true),
            default => true,
        };
    }
}
