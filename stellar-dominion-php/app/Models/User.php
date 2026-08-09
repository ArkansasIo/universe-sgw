<?php

declare(strict_types=1);

namespace StellarDominion\Models;

use StellarDominion\Core\Database;

/**
 * User model — maps the users table.
 */
final class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $username,
        public readonly string $email,
        public readonly string $passwordHash,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly bool $isAdmin = false,
    ) {}

    public static function find(string $id): ?self
    {
        $row = Database::connection()
            ->query('SELECT * FROM users WHERE id = ?', [$id])
            ->fetch();
        return $row !== false ? self::fromRow($row) : null;
    }

    public static function findByUsername(string $username): ?self
    {
        $row = Database::connection()
            ->query('SELECT * FROM users WHERE username = ? OR email = ?', [$username, $username])
            ->fetch();
        return $row !== false ? self::fromRow($row) : null;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id: $row['id'],
            username: $row['username'],
            email: $row['email'],
            passwordHash: $row['password_hash'],
            firstName: $row['first_name'] ?? null,
            lastName: $row['last_name'] ?? null,
            isAdmin: (bool) ($row['is_admin'] ?? false),
        );
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->passwordHash);
    }

    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'isAdmin' => $this->isAdmin,
        ];
    }
}
