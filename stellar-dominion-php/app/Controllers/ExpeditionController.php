<?php

declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Core\Database;
use StellarDominion\Core\HttpException;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;
use StellarDominion\Engine\ExpeditionEngine;
use StellarDominion\Models\PlayerState;

/**
 * Expedition endpoints.
 *
 * Port of routes-expeditions.ts. Launches an expedition from the player's
 * current fleet and lists past expeditions.
 */
final class ExpeditionController extends BaseController
{
    public function index(Request $request): Response
    {
        $user = $this->currentUser($request);
        $rows = Database::connection()
            ->query('SELECT * FROM expeditions WHERE user_id = ? ORDER BY created_at DESC', [$user->id])
            ->fetchAll();
        return $this->json(array_map([$this, 'hydrate'], $rows));
    }

    public function launch(Request $request): Response
    {
        $user = $this->currentUser($request);
        $state = PlayerState::forUser($user->id);
        if ($state === null) {
            throw HttpException::notFound('Player state not found.');
        }

        $units = $state->units();
        if ($units === []) {
            throw HttpException::badRequest('No fleet available.');
        }

        $db = Database::connection();
        $id = \StellarDominion\Core\UUID::v4();
        $db->prepare(
            'INSERT INTO expeditions (id, user_id, name, type, tier, fleet_comp, status, started_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        )->execute([
            $id,
            $user->id,
            (string) $request->input('name', 'Expedition'),
            (string) $request->input('type', 'exploration'),
            (int) $request->input('tier', 1),
            json_encode($units),
            'active',
        ]);

        return $this->json(['id' => $id], 'Expedition launched', 202);
    }

    /** Resolve one expedition immediately (dev/testing helper). */
    public function resolve(Request $request, string $id): Response
    {
        $user = $this->currentUser($request);
        $db = Database::connection();
        $row = $db->query('SELECT * FROM expeditions WHERE id = ? AND user_id = ?', [$id, $user->id])->fetch();
        if ($row === false) {
            throw HttpException::notFound('Expedition not found.');
        }

        $fleet = $row['fleet_comp'] ? json_decode($row['fleet_comp'], true) : [];
        $encounter = ExpeditionEngine::generateEncounter(is_array($fleet) ? $fleet : [], (int) $row['tier']);

        $discoveries = $row['discoveries'] ? json_decode($row['discoveries'], true) : [];
        $discoveries[] = $encounter;

        $db->prepare("UPDATE expeditions SET discoveries = ?, status = 'completed', completed_at = NOW() WHERE id = ?")
            ->execute([json_encode($discoveries), $id]);

        return $this->json($this->hydrate($db->query('SELECT * FROM expeditions WHERE id = ?', [$id])->fetch()));
    }

    private function hydrate(array $row): array
    {
        foreach (['fleet_comp', 'troop_comp', 'discoveries', 'casualties'] as $col) {
            if (isset($row[$col]) && is_string($row[$col])) {
                $row[$col] = json_decode($row[$col], true);
            }
        }
        return $row;
    }
}
