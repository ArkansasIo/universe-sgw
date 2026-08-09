<?php

declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Core\Auth;
use StellarDominion\Core\HttpException;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;
use StellarDominion\Core\Validator;

/**
 * Authentication endpoints.
 *
 * Port of server/basicAuth.ts + routes-account.ts (register/login/logout).
 */
final class AuthController extends BaseController
{
    public function register(Request $request): Response
    {
        $data = $request->all();
        $v = Validator::make($data)
            ->string('username', 3, 50, 'Username must be 3-50 characters.')
            ->string('password', 8, 128, 'Password must be at least 8 characters.')
            ->email('email', 'A valid email is required.');

        if (!$v->passes()) {
            throw HttpException::unprocessable('Validation failed', $v->errors());
        }

        $user = Auth::register($data['username'], $data['email'], $data['password']);
        Auth::login($user);

        return $this->json($user->toPublicArray(), 'Registered', 201);
    }

    public function login(Request $request): Response
    {
        $data = $request->all();
        $identifier = (string) ($data['username'] ?? $data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');

        $user = Auth::attempt($identifier, $password);
        if ($user === null) {
            throw HttpException::unauthorized('Invalid credentials.');
        }

        Auth::login($user);
        return $this->json($user->toPublicArray(), 'Logged in');
    }

    public function logout(Request $request): Response
    {
        Auth::logout();
        return $this->json([], 'Logged out');
    }

    public function me(Request $request): Response
    {
        $user = $this->currentUser($request);
        return $this->json($user->toPublicArray());
    }
}
