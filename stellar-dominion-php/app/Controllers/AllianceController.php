<?php

declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Core\Database;
use StellarDominion\Core\HttpException;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;
use StellarDominion\Engine\DiplomacyEngine;

/**
 * Alliance endpoints.
 *
 * Port of routes-alliances.ts + allianceService.
 */
final class AllianceController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->currentUser($request);
        $alliances = Database::connection()
            ->query('SELECT a.*, (SELECT COUNT(*) FROM alliance_members am WHERE am.alliance_id = a.id) AS member_count FROM alliances a ORDER BY a.name ASC')
            ->fetchAll();
        return $this->json($alliances);
    }

    public function show(Request $request, string $id): Response
    {
        $this->currentUser($request);
        $alliance = DiplomacyEngine::find($id);
        if ($alliance === null) {
            throw HttpException::notFound('Alliance not found.');
        }
        $alliance['members'] = DiplomacyEngine::members($id);
        if (is_string($alliance['resources'] ?? null)) {
            $alliance['resources'] = json_decode($alliance['resources'], true);
        }
        return $this->json($alliance);
    }

    public function create(Request $request): Response
    {
        $user = $this->currentUser($request);
        try {
            $alliance = DiplomacyEngine::create(
                $user->id,
                (string) $request->input('name', ''),
                (string) $request->input('tag', ''),
                $request->input('description'),
            );
        } catch (\RuntimeException $e) {
            throw HttpException::unprocessable($e->getMessage());
        }
        return $this->json($alliance, 'Alliance created', 201);
    }

    public function join(Request $request, string $id): Response
    {
        $user = $this->currentUser($request);
        try {
            DiplomacyEngine::join($id, $user->id);
        } catch (\RuntimeException $e) {
            throw HttpException::unprocessable($e->getMessage());
        }
        return $this->json(['joined' => $id]);
    }

    public function leave(Request $request, string $id): Response
    {
        $user = $this->currentUser($request);
        try {
            DiplomacyEngine::leave($id, $user->id);
        } catch (\RuntimeException $e) {
            throw HttpException::unprocessable($e->getMessage());
        }
        return $this->json(['left' => $id]);
    }

    public function setRank(Request $request, string $id, string $userId): Response
    {
        $actor = $this->currentUser($request);
        try {
            DiplomacyEngine::setRank($id, $actor->id, $userId, (string) $request->input('rank', 'member'));
        } catch (\RuntimeException $e) {
            throw HttpException::unprocessable($e->getMessage());
        }
        return $this->json(['rankUpdated' => true]);
    }

    public function contribute(Request $request, string $id): Response
    {
        $user = $this->currentUser($request);
        try {
            $treasury = DiplomacyEngine::contribute($id, $user->id, $request->input('resources', []));
        } catch (\RuntimeException $e) {
            throw HttpException::unprocessable($e->getMessage());
        }
        return $this->json(['treasury' => $treasury]);
    }
}
