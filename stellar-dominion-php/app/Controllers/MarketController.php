<?php

declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Core\Database;
use StellarDominion\Core\HttpException;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;
use StellarDominion\Engine\EconomyEngine;

/**
 * Market endpoints: place orders, list the order book, cancel.
 *
 * Port of routes-resource-trading.ts + market tick services.
 */
final class MarketController extends BaseController
{
    public function index(Request $request): Response
    {
        $this->currentUser($request);
        $resource = $request->query('resource');
        $where = $resource ? 'WHERE resource = ?' : '';
        $params = $resource ? [$resource] : [];

        $orders = Database::connection()
            ->query("SELECT * FROM market_orders $where ORDER BY price_per_unit ASC", $params)
            ->fetchAll();

        return $this->json(array_map([$this, 'hydrate'], $orders));
    }

    public function place(Request $request): Response
    {
        $user = $this->currentUser($request);

        try {
            $result = EconomyEngine::placeOrder(
                $user->id,
                (string) $request->input('type', 'sell'),
                (string) $request->input('resource', 'metal'),
                (int) $request->input('amount', 0),
                (float) $request->input('price_per_unit', 0),
            );
        } catch (\RuntimeException $e) {
            throw HttpException::unprocessable($e->getMessage());
        }

        return $this->json($result, 'Order placed', 201);
    }

    public function cancel(Request $request, string $id): Response
    {
        $user = $this->currentUser($request);
        $db = Database::connection();
        $row = $db->query('SELECT * FROM market_orders WHERE id = ? AND user_id = ?', [$id, $user->id])->fetch();
        if ($row === false) {
            throw HttpException::notFound('Order not found.');
        }
        $db->prepare("UPDATE market_orders SET status = 'cancelled' WHERE id = ?")->execute([$id]);
        return $this->json(['cancelled' => $id]);
    }

    public function myOrders(Request $request): Response
    {
        $user = $this->currentUser($request);
        $rows = Database::connection()
            ->query('SELECT * FROM market_orders WHERE user_id = ? ORDER BY created_at DESC', [$user->id])
            ->fetchAll();
        return $this->json(array_map([$this, 'hydrate'], $rows));
    }

    private function hydrate(array $row): array
    {
        return $row;
    }
}
