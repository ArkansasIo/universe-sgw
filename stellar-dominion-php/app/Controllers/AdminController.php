<?php

declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Core\Database;
use StellarDominion\Core\HttpException;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;
use StellarDominion\Models\User;

/**
 * Admin endpoints (user management, server status, game settings).
 *
 * Port of routes-admin.ts + admin-manager.ts.
 */
final class AdminController extends BaseController
{
    private function requireAdmin(Request $request): User
    {
        $user = $this->currentUser($request);
        if (!$user->isAdmin) {
            throw HttpException::forbidden('Admin access required.');
        }
        return $user;
    }

    public function status(Request $request): Response
    {
        $this->requireAdmin($request);
        $db = Database::connection();

        return $this->json([
            'users' => (int) $db->query('SELECT COUNT(*) AS c FROM users')->fetch()['c'],
            'players' => (int) $db->query('SELECT COUNT(*) AS c FROM player_states')->fetch()['c'],
            'activeMissions' => (int) $db->query("SELECT COUNT(*) AS c FROM missions WHERE status = 'outbound'")->fetch()['c'],
            'battles' => (int) $db->query('SELECT COUNT(*) AS c FROM battles')->fetch()['c'],
            'marketOrders' => (int) $db->query("SELECT COUNT(*) AS c FROM market_orders WHERE status = 'active'")->fetch()['c'],
            'alliances' => (int) $db->query('SELECT COUNT(*) AS c FROM alliances')->fetch()['c'],
            'lastTicks' => $db->query('SELECT * FROM server_game_ticks ORDER BY id DESC LIMIT 20')->fetchAll(),
        ]);
    }

    public function listUsers(Request $request): Response
    {
        $this->requireAdmin($request);
        $users = Database::connection()
            ->query('SELECT id, username, email, is_admin, last_login_at, created_at FROM users ORDER BY created_at DESC LIMIT 100')
            ->fetchAll();
        return $this->json(['users' => $users]);
    }

    public function setAdmin(Request $request, string $id): Response
    {
        $this->requireAdmin($request);
        $isAdmin = (bool) $request->input('is_admin', true);
        Database::connection()->prepare('UPDATE users SET is_admin = ? WHERE id = ?')->execute([$isAdmin ? 1 : 0, $id]);
        return $this->json(['updated' => $id]);
    }
}
