<?php

declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Core\Auth;
use StellarDominion\Core\HttpException;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;
use StellarDominion\Models\User;

/**
 * Base controller with shared helpers.
 */
abstract class BaseController
{
    protected function currentUser(Request $request): User
    {
        $user = Auth::user();
        if ($user === null) {
            throw HttpException::unauthorized();
        }
        return $user;
    }

    protected function json(mixed $data = [], string $message = 'OK', int $status = 200): Response
    {
        return Response::make($status)->success($data, $message);
    }

    protected function error(string $message, int $status = 500, array $details = []): Response
    {
        return Response::make($status)->error($message, $details);
    }
}
