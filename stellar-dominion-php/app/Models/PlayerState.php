<?php

declare(strict_types=1);

namespace StellarDominion\Models;

use StellarDominion\Core\Database;
use StellarDominion\Engine\ResourceEngine;

/**
 * PlayerState model — maps the player_states "mega table".
 *
 * JSON columns are lazily decoded and re-encoded on save, mirroring the
 * original Drizzle/Postgres jsonb columns.
 */
final class PlayerState
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        private array $data,
    ) {}

    public static function forUser(string $userId): ?self
    {
        $row = Database::connection()
            ->query('SELECT * FROM player_states WHERE user_id = ?', [$userId])
            ->fetch();
        return $row !== false ? new self($row['id'], $row['user_id'], $row) : null;
    }

    public static function find(string $id): ?self
    {
        $row = Database::connection()
            ->query('SELECT * FROM player_states WHERE id = ?', [$id])
            ->fetch();
        return $row !== false ? new self($row['id'], $row['user_id'], $row) : null;
    }

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    /** Decode a JSON column. */
    public function json(string $column): array
    {
        $raw = $this->data[$column] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = json_decode((string) ($raw ?? '{}'), true);
        return is_array($decoded) ? $decoded : [];
    }

    public function resources(): array
    {
        return $this->json('resources');
    }

    public function buildings(): array
    {
        return $this->json('buildings');
    }

    public function research(): array
    {
        return $this->json('research');
    }

    public function units(): array
    {
        return $this->json('units');
    }

    /** Apply resource production for elapsed time and persist. */
    public function refreshResources(): array
    {
        $nowMs = (int) floor(microtime(true) * 1000);
        $result = ResourceEngine::produce($this->data, $nowMs);

        $this->data['resources'] = json_encode($result['resources']);
        $this->data['last_resource_update'] = $result['last_resource_update'];

        Database::connection()
            ->prepare('UPDATE player_states SET resources = ?, last_resource_update = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($result['resources']), $result['last_resource_update'], $this->id]);

        return $result['resources'];
    }

    /** Persist a single JSON column. */
    public function saveJson(string $column, array $value): void
    {
        $this->data[$column] = $value;
        $db = Database::connection();
        $cols = $db->query('SHOW COLUMNS FROM player_states LIKE ?', [$column])->fetch();
        if ($cols === false) {
            throw new \InvalidArgumentException("Unknown column: $column");
        }
        $db->prepare('UPDATE player_states SET `' . $column . '` = ?, updated_at = NOW() WHERE id = ?')
            ->execute([json_encode($value), $this->id]);
    }

    public function toArray(): array
    {
        $out = [];
        foreach ($this->data as $key => $value) {
            if (in_array($key, ['resources', 'buildings', 'orbital_buildings', 'cron_jobs', 'research', 'research_queue', 'research_history', 'active_research', 'research_bonuses', 'research_lab', 'turns_data', 'units', 'known_planets', 'travel_state', 'travel_log', 'prestige_bonus', 'missile_silo', 'moons_data', 'terraformer', 'active_officers', 'artifacts', 'ship_fittings', 'occupations', 'occupying'], true)) {
                $out[$key] = json_decode((string) ($value ?? '{}'), true);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }
}
