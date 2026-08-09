<?php

declare(strict_types=1);

namespace StellarDominion\Core;

use StellarDominion\Models\User;

/**
 * Session + password auth.
 *
 * Port of server/basicAuth.ts (express-session + bcrypt). Uses PHP native
 * sessions under the hood; stores the logged-in user id in the session and
 * hashes passwords with password_hash()/bcrypt.
 */
final class Auth
{
    public const SESSION_KEY = 'sd_user_id';

    public static function boot(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name(Config::get('auth.cookie', 'sd_session'));
        session_set_cookie_params([
            'httponly' => true,
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
            'lifetime' => (int) Config::get('auth.session_ttl_seconds', 604800),
        ]);
        session_start();
    }

    /** Register a new account and create the default player state. */
    public static function register(string $username, string $email, string $password): User
    {
        if (strlen($password) < (int) Config::get('security.password_min_length', 8)) {
            throw new \InvalidArgumentException('Password must be at least 8 characters.');
        }

        $pdo = Database::connection();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => (int) Config::get('auth.bcrypt_cost', 12)]);
        $id = UUID::v4();

        try {
            Database::transaction(function () use ($pdo, $id, $username, $email, $hash) {
                $stmt = $pdo->prepare(
                    'INSERT INTO users (id, username, password_hash, email, created_at, updated_at)
                     VALUES (:id, :username, :hash, :email, NOW(), NOW())'
                );
                $stmt->execute(['id' => $id, 'username' => $username, 'hash' => $hash, 'email' => $email]);

                $pdo->prepare(
                    'INSERT INTO player_states (user_id, created_at, updated_at)
                     VALUES (:id, NOW(), NOW())'
                )->execute(['id' => $id]);
            });
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                throw new \InvalidArgumentException('Username or email already in use.');
            }
            throw $e;
        }

        return User::find($id);
    }

    public static function attempt(string $username, string $password): ?User
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :u OR email = :u LIMIT 1');
        $stmt->execute(['u' => $username]);
        $row = $stmt->fetch();

        if (!$row || !password_verify($password, $row['password_hash'])) {
            return null;
        }

        return User::fromRow($row);
    }

    public static function login(User $user): void
    {
        self::boot();
        $_SESSION[self::SESSION_KEY] = $user->id;
        $pdo = Database::connection();
        $pdo->prepare('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = :id')
            ->execute(['id' => $user->id]);
    }

    public static function logout(): void
    {
        self::boot();
        unset($_SESSION[self::SESSION_KEY]);
        session_regenerate_id(true);
    }

    public static function user(): ?User
    {
        self::boot();
        $id = $_SESSION[self::SESSION_KEY] ?? null;
        return $id ? User::find((string) $id) : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    /** Middleware factory: returns a callable that blocks unauthenticated requests. */
    public static function requireAuth(): callable
    {
        return function (Request $request): ?Response {
            if (!self::check()) {
                return Response::make(401)->json(['status' => 'error', 'message' => 'Unauthorized']);
            }
            return null;
        };
    }
}
