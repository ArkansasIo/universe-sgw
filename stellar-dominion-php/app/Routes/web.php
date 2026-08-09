<?php

declare(strict_types=1);

/**
 * Route registration — the single router definition for the app.
 *
 * Mirrors server/index.ts + routes.ts route registration. All endpoints
 * under /api are JSON.
 */

use StellarDominion\Controllers\AdminController;
use StellarDominion\Controllers\AllianceController;
use StellarDominion\Controllers\AuthController;
use StellarDominion\Controllers\BankController;
use StellarDominion\Controllers\CombatController;
use StellarDominion\Controllers\ConstructionController;
use StellarDominion\Controllers\ExpeditionController;
use StellarDominion\Controllers\FleetController;
use StellarDominion\Controllers\GalaxyController;
use StellarDominion\Controllers\MarketController;
use StellarDominion\Controllers\PlayerController;
use StellarDominion\Controllers\ResearchController;
use StellarDominion\Core\Auth;
use StellarDominion\Core\Config;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;
use StellarDominion\Core\Router;

$router = new Router();

// Basic rate limiting (per-IP in-memory) — port of the express-rate-limit guard
$rateBuckets = [];
$router->middleware(function (Request $request) use (&$rateBuckets): ?Response {
    $perMinute = (int) Config::get('security.rate_limit_api_per_minute', 120);
    $ip = $request->ip();
    $minute = (int) floor(time() / 60);
    $key = "$ip:$minute";
    $rateBuckets[$key] = ($rateBuckets[$key] ?? 0) + 1;
    if (count($rateBuckets) > 10000) {
        $rateBuckets = array_slice($rateBuckets, -5000, null, true);
    }
    if ($rateBuckets[$key] > $perMinute) {
        return Response::make(429)->json(['status' => 'error', 'message' => 'Rate limit exceeded']);
    }
    return null;
});

// Health check (no auth)
$router->get('/', fn (Request $request): Response => Response::make()->success([
    'name' => Config::get('app.name', 'Stellar Dominion'),
    'version' => Config::get('app.version', '1.0.0-php'),
    'time' => date('c'),
]));

$router->get('/api/health', fn (Request $request): Response => Response::make()->success(['status' => 'ok']));

// Auth (public)
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout']);
$router->get('/api/auth/me', [AuthController::class, 'me']);

// All /api routes except /api/auth require authentication
$router->middleware(function (Request $request): ?Response {
    $path = $request->path();
    if (str_starts_with($path, '/api/') && !str_starts_with($path, '/api/auth/')) {
        if (!Auth::check()) {
            return Response::make(401)->json(['status' => 'error', 'message' => 'Unauthorized']);
        }
    }
    return null;
});

// Authenticated API
$router->group('/api', function (Router $r): void {

    // Player state
    $r->get('/player', [PlayerController::class, 'show']);
    $r->get('/player/resources', [PlayerController::class, 'resources']);
    $r->get('/player/buildings', [PlayerController::class, 'buildings']);
    $r->get('/player/research', [PlayerController::class, 'research']);
    $r->get('/player/units', [PlayerController::class, 'units']);
    $r->get('/player/storage', [PlayerController::class, 'storageCapacity']);

    // Construction
    $r->get('/buildings', [ConstructionController::class, 'index']);
    $r->post('/buildings/{buildingKey}/build', [ConstructionController::class, 'build']);
    $r->post('/buildings/cancel', [ConstructionController::class, 'cancel']);

    // Research
    $r->get('/research', [ResearchController::class, 'index']);
    $r->post('/research/{techKey}/start', [ResearchController::class, 'start']);

    // Fleet
    $r->get('/fleet/catalog', [FleetController::class, 'catalog']);
    $r->post('/fleet/build', [FleetController::class, 'build']);
    $r->post('/fleet/launch', [FleetController::class, 'launch']);
    $r->get('/fleet', [FleetController::class, 'list']);

    // Galaxy
    $r->get('/galaxy', [GalaxyController::class, 'view']);

    // Combat reports
    $r->get('/combat', [CombatController::class, 'index']);
    $r->get('/combat/{id}', [CombatController::class, 'show']);

    // Expeditions
    $r->get('/expeditions', [ExpeditionController::class, 'index']);
    $r->post('/expeditions', [ExpeditionController::class, 'launch']);
    $r->post('/expeditions/{id}/resolve', [ExpeditionController::class, 'resolve']);

    // Market
    $r->get('/market', [MarketController::class, 'index']);
    $r->get('/market/my', [MarketController::class, 'myOrders']);
    $r->post('/market/orders', [MarketController::class, 'place']);
    $r->post('/market/orders/{id}/cancel', [MarketController::class, 'cancel']);

    // Bank
    $r->get('/bank', [BankController::class, 'account']);
    $r->post('/bank/deposit', [BankController::class, 'deposit']);
    $r->post('/bank/withdraw', [BankController::class, 'withdraw']);

    // Alliances
    $r->get('/alliances', [AllianceController::class, 'index']);
    $r->get('/alliances/{id}', [AllianceController::class, 'show']);
    $r->post('/alliances', [AllianceController::class, 'create']);
    $r->post('/alliances/{id}/join', [AllianceController::class, 'join']);
    $r->post('/alliances/{id}/leave', [AllianceController::class, 'leave']);
    $r->post('/alliances/{id}/rank/{userId}', [AllianceController::class, 'setRank']);
    $r->post('/alliances/{id}/contribute', [AllianceController::class, 'contribute']);
});

// Admin
$router->group('/api/admin', function (Router $r): void {
    $r->get('/status', [AdminController::class, 'status']);
    $r->get('/users', [AdminController::class, 'listUsers']);
    $r->post('/users/{id}/admin', [AdminController::class, 'setAdmin']);
});

// 404 catch-all for any unmatched /api route
$router->middleware(function (Request $request): ?Response {
    return null;
});

return $router;
