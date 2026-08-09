<?php

declare(strict_types=1);

namespace StellarDominion\Engine;

use StellarDominion\Core\Database;

/**
 * Diplomacy: alliances.
 *
 * Port of routes-alliances.ts / allianceService.ts. Supports create, join,
 * member ranks, and treasury contributions.
 */
final class DiplomacyEngine
{
    public const RANKS = ['leader', 'officer', 'member', 'recruit'];

    public static function create(string $leaderId, string $name, string $tag, ?string $description = null): array
    {
        $db = Database::connection();

        $dup = $db->query('SELECT id FROM alliances WHERE name = ? OR tag = ?', [$name, $tag])->fetch();
        if ($dup !== false) {
            throw new \RuntimeException('Alliance name or tag already in use.');
        }

        $id = \StellarDominion\Core\UUID::v4();
        $db->prepare(
            'INSERT INTO alliances (id, name, tag, description, resources)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([$id, $name, $tag, $description, json_encode(['metal' => 0, 'crystal' => 0, 'deuterium' => 0])]);

        $db->prepare(
            'INSERT INTO alliance_members (id, alliance_id, user_id, rank)
             VALUES (?, ?, ?, ?)'
        )->execute([\StellarDominion\Core\UUID::v4(), $id, $leaderId, 'leader']);

        return self::find($id);
    }

    public static function find(string $allianceId): ?array
    {
        $row = Database::connection()->query('SELECT * FROM alliances WHERE id = ?', [$allianceId])->fetch();
        return $row !== false ? $row : null;
    }

    public static function findByName(string $name): ?array
    {
        $row = Database::connection()->query('SELECT * FROM alliances WHERE name = ?', [$name])->fetch();
        return $row !== false ? $row : null;
    }

    public static function members(string $allianceId): array
    {
        return Database::connection()->query(
            'SELECT am.*, u.username FROM alliance_members am
             JOIN users u ON u.id = am.user_id
             WHERE am.alliance_id = ? ORDER BY FIELD(am.rank, "leader", "officer", "member", "recruit")',
            [$allianceId]
        )->fetchAll();
    }

    public static function memberRank(string $allianceId, string $userId): ?string
    {
        $row = Database::connection()->query(
            'SELECT rank FROM alliance_members WHERE alliance_id = ? AND user_id = ?',
            [$allianceId, $userId]
        )->fetch();
        return $row !== false ? $row['rank'] : null;
    }

    public static function join(string $allianceId, string $userId): void
    {
        $db = Database::connection();
        $existing = $db->query('SELECT alliance_id FROM alliance_members WHERE user_id = ?', [$userId])->fetch();
        if ($existing !== false) {
            throw new \RuntimeException('Already in an alliance.');
        }
        $db->prepare('INSERT INTO alliance_members (id, alliance_id, user_id, rank) VALUES (?, ?, ?, ?)')
            ->execute([\StellarDominion\Core\UUID::v4(), $allianceId, $userId, 'recruit']);
    }

    public static function leave(string $allianceId, string $userId): void
    {
        $db = Database::connection();
        $member = $db->query(
            'SELECT * FROM alliance_members WHERE alliance_id = ? AND user_id = ?',
            [$allianceId, $userId]
        )->fetch();
        if ($member === false) {
            throw new \RuntimeException('Not a member.');
        }
        if ($member['rank'] === 'leader') {
            throw new \RuntimeException('Leader must transfer leadership before leaving.');
        }
        $db->prepare('DELETE FROM alliance_members WHERE alliance_id = ? AND user_id = ?')
            ->execute([$allianceId, $userId]);
    }

    /** Set a member's rank (leader or officer only). */
    public static function setRank(string $allianceId, string $actorId, string $targetUserId, string $newRank): void
    {
        if (!in_array($newRank, self::RANKS, true)) {
            throw new \RuntimeException('Invalid rank.');
        }
        $db = Database::connection();
        $actorRank = self::memberRank($allianceId, $actorId);
        if (!in_array($actorRank, ['leader', 'officer'], true)) {
            throw new \RuntimeException('Insufficient permissions.');
        }
        if ($newRank === 'leader' && $actorRank !== 'leader') {
            throw new \RuntimeException('Only the leader can transfer leadership.');
        }
        $db->prepare('UPDATE alliance_members SET rank = ? WHERE alliance_id = ? AND user_id = ?')
            ->execute([$newRank, $allianceId, $targetUserId]);
    }

    /** Contribute resources to the alliance treasury. */
    public static function contribute(string $allianceId, string $userId, array $resources): array
    {
        $db = Database::connection();
        $rank = self::memberRank($allianceId, $userId);
        if ($rank === null) {
            throw new \RuntimeException('Not a member.');
        }

        $row = $db->query('SELECT resources FROM player_states WHERE id = ?', [$userId])->fetch();
        $playerResources = $row['resources'] ? json_decode($row['resources'], true) : [];
        $allianceResources = $db->query('SELECT resources FROM alliances WHERE id = ?', [$allianceId])->fetch();
        $treasury = $allianceResources['resources'] ? json_decode($allianceResources['resources'], true) : [];

        foreach (['metal', 'crystal', 'deuterium'] as $res) {
            $amount = (int) ($resources[$res] ?? 0);
            if ($amount <= 0) {
                continue;
            }
            if (($playerResources[$res] ?? 0) < $amount) {
                throw new \RuntimeException("Insufficient $res.");
            }
            $playerResources[$res] -= $amount;
            $treasury[$res] = (int) ($treasury[$res] ?? 0) + $amount;
        }

        $db->prepare('UPDATE player_states SET resources = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($playerResources), $userId]);
        $db->prepare('UPDATE alliances SET resources = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($treasury), $allianceId]);

        $db->prepare('UPDATE alliance_members SET points = points + ? WHERE alliance_id = ? AND user_id = ?')
            ->execute([array_sum($resources), $allianceId, $userId]);

        return $treasury;
    }
}
