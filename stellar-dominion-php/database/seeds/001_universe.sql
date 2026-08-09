-- Default universe (single playable universe, 9 galaxies as configured)
INSERT INTO universes (id, name, realm_id, seed, galaxy_count)
VALUES ('00000000-0000-0000-0000-000000000001', 'Milky Way Alpha', NULL, 1337, 9);

-- Starter star system [1:1:1] hosting new players
INSERT INTO star_systems (id, universe_id, galaxy, sector, system, name, coordinates)
VALUES (
  '00000000-0000-0000-0000-000000000002',
  '00000000-0000-0000-0000-000000000001',
  1, 1, 1,
  'Sol Prime',
  '[1:1:1]'
);

-- Starter planet (owned by no one yet; assigned on registration)
INSERT INTO planets (
  id, system_id, name, planet_type, position, size, temperature,
  owner_id, metal_richness, crystal_richness, deuterium_richness
)
VALUES (
  '00000000-0000-0000-0000-000000000003',
  '00000000-0000-0000-0000-000000000002',
  'Homeworld',
  'terrestrial',
  3,
  163, 15,
  NULL,
  1.0, 1.0, 1.0
);
