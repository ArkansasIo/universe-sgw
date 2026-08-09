<?php

declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Core\Database;
use StellarDominion\Core\HttpException;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;

/**
 * Galaxy / universe exploration endpoints.
 *
 * Port of routes-galaxy.ts. Lists systems and planets for a coordinate.
 */
final class GalaxyController extends BaseController
{
    public function view(Request $request): Response
    {
        $this->currentUser($request);

        $galaxy = (int) $request->input('galaxy', 1);
        $sector = (int) $request->input('sector', 1);
        $system = (int) $request->input('system', 1);

        $db = Database::connection();
        $row = $db->query(
            'SELECT id, name, coordinates FROM star_systems WHERE galaxy = ? AND sector = ? AND system = ? LIMIT 1',
            [$galaxy, $sector, $system]
        )->fetch();

        if ($row === false) {
            throw HttpException::notFound('System not found.');
        }

        $planets = $db->query(
            'SELECT p.*, u.username
             FROM planets p
             LEFT JOIN users u ON u.id = p.owner_id
             WHERE p.system_id = ? ORDER BY p.position ASC',
            [$row['id']]
        )->fetchAll();

        return $this->json([
            'system' => $row,
            'planets' => array_map(static function (array $p): array {
                if (isset($p['owner_id']) && is_string($p['owner_id'])) {
                    $p['owned'] = true;
                }
                unset($p['owner_id']);
                return $p;
            }, $planets),
        ]);
    }
}
