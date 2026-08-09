<?php

declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Core\HttpException;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;
use StellarDominion\Models\Battle;

/**
 * Combat report endpoints.
 *
 * Port of routes-combat.ts / routes-ogame-combat.ts.
 */
final class CombatController extends BaseController
{
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);
        return $this->json(['battles' => Battle::forPlayer($user->id)]);
    }

    public function show(Request $request, string $id): Response
    {
        $user = $this->currentUser($request);
        $battle = Battle::find($id);
        if ($battle === null) {
            throw HttpException::notFound('Battle not found.');
        }
        if ($battle['attacker_id'] !== $user->id && $battle['defender_id'] !== $user->id) {
            throw HttpException::forbidden();
        }
        return $this->json($battle);
    }
}
