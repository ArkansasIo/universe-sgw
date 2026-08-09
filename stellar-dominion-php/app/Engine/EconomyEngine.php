<?php

declare(strict_types=1);

namespace StellarDominion\Engine;

use StellarDominion\Core\Config;
use StellarDominion\Core\Database;

/**
 * Economy: market orders, bank accounts, currency.
 *
 * Port of routes-market / bank-vault / currency services. Market orders use
 * a simple order-book with fee; bank pays periodic interest.
 */
final class EconomyEngine
{
    public const RESOURCES = ['metal', 'crystal', 'deuterium'];

    /** Place a buy/sell order. Fee is charged on the transaction value. */
    public static function placeOrder(string $playerId, string $type, string $resource, int $amount, float $pricePerUnit): array
    {
        if (!in_array($resource, self::RESOURCES, true)) {
            throw new \RuntimeException('Invalid resource.');
        }
        if ($amount <= 0 || $pricePerUnit <= 0) {
            throw new \RuntimeException('Invalid amount or price.');
        }

        $db = Database::connection();
        $feePercent = (float) Config::get('game.economy.market_fee_percent', 0.05);

        $id = \StellarDominion\Core\UUID::v4();
        $db->prepare(
            'INSERT INTO market_orders (id, user_id, type, resource, amount, price_per_unit, status)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([$id, $playerId, $type, $resource, $amount, $pricePerUnit, 'active']);

        $matched = self::matchOrder($id);

        return ['orderId' => $id, 'matched' => $matched];
    }

    /** Match a new order against the opposite side of the book. */
    public static function matchOrder(string $orderId): int
    {
        $db = Database::connection();
        $stmt = $db->prepare('SELECT * FROM market_orders WHERE id = ?');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if ($order === false || $order['status'] !== 'active') {
            return 0;
        }

        $opposite = $order['type'] === 'buy' ? 'sell' : 'buy';
        $priceFilter = $order['type'] === 'buy' ? 'price_per_unit <= ?' : 'price_per_unit >= ?';

        $matches = $db->query(
            "SELECT * FROM market_orders
             WHERE id <> '{$order['id']}' AND status = 'active' AND type = '{$opposite}'
               AND resource = '{$order['resource']}' AND {$priceFilter}
             ORDER BY price_per_unit " . ($order['type'] === 'buy' ? 'ASC' : 'DESC') . ' LIMIT 50',
            [$order['price_per_unit']]
        )->fetchAll();

        $matchedCount = 0;

        foreach ($matches as $match) {
            if ($order['filled_amount'] >= $order['amount']) {
                break;
            }
            $remainingOrder = $order['amount'] - $order['filled_amount'];
            $remainingMatch = $match['amount'] - $match['filled_amount'];
            $tradeQty = min($remainingOrder, $remainingMatch);
            if ($tradeQty <= 0) {
                continue;
            }

            self::executeTrade($order, $match, (int) $tradeQty);

            $order['filled_amount'] += $tradeQty;
            $match['filled_amount'] += $tradeQty;
            $matchedCount++;

            if ($order['filled_amount'] >= $order['amount']) {
                $db->prepare("UPDATE market_orders SET status = 'completed', completed_at = NOW() WHERE id = ?")
                    ->execute([$order['id']]);
            }
            if ($match['filled_amount'] >= $match['amount']) {
                $db->prepare("UPDATE market_orders SET status = 'completed', completed_at = NOW() WHERE id = ?")
                    ->execute([$match['id']]);
            } else {
                $db->prepare('UPDATE market_orders SET filled_amount = ? WHERE id = ?')
                    ->execute([$match['filled_amount'], $match['id']]);
            }
        }

        if ($order['filled_amount'] >= $order['amount']) {
            $db->prepare("UPDATE market_orders SET status = 'completed', completed_at = NOW() WHERE id = ?")
                ->execute([$order['id']]);
        } else {
            $db->prepare('UPDATE market_orders SET filled_amount = ? WHERE id = ?')
                ->execute([$order['filled_amount'], $order['id']]);
        }

        return $matchedCount;
    }

    /**
     * Execute one trade: buyer pays credits, seller pays resource.
     * Settlement transfers resources from seller to buyer.
     */
    private static function executeTrade(array $buyOrder, array $sellOrder, int $qty): void
    {
        $db = Database::connection();
        $buyerId = $buyOrder['type'] === 'buy' ? $buyOrder['user_id'] : $sellOrder['user_id'];
        $sellerId = $buyOrder['type'] === 'buy' ? $sellOrder['user_id'] : $buyOrder['user_id'];
        $buyerOrder = $buyOrder['type'] === 'buy' ? $buyOrder : $sellOrder;
        $sellerOrder = $buyOrder['type'] === 'buy' ? $sellOrder : $buyOrder;

        $price = $buyerOrder['price_per_unit'];
        $credits = (int) floor($qty * $price);

        $db->prepare(
            'UPDATE player_states
             SET resources = JSON_SET(resources, "$.credits", GREATEST(0, COALESCE(JSON_EXTRACT(resources, "$.credits"), 0) - ?)), updated_at = NOW()
             WHERE id = ?'
        )->execute([$credits, $buyerId]);

        $db->prepare(
            'UPDATE player_states
             SET resources = JSON_SET(resources, "$.' . $buyerOrder['resource'] . '", COALESCE(JSON_EXTRACT(resources, "$.' . $buyerOrder['resource'] . '"), 0) + ?), updated_at = NOW()
             WHERE id = ?'
        )->execute([$qty, $buyerId]);

        $db->prepare(
            'UPDATE player_states
             SET resources = JSON_SET(resources, "$.credits", COALESCE(JSON_EXTRACT(resources, "$.credits"), 0) + ?), updated_at = NOW()
             WHERE id = ?'
        )->execute([$credits, $sellerId]);

        $db->prepare(
            'UPDATE player_states
             SET resources = JSON_SET(resources, "$.' . $sellerOrder['resource'] . '", GREATEST(0, COALESCE(JSON_EXTRACT(resources, "$.' . $sellerOrder['resource'] . '"), 0) - ?)), updated_at = NOW()
             WHERE id = ?'
        )->execute([$qty, $sellerId]);
    }

    // ------------------------------------------------------------------
    // Bank
    // ------------------------------------------------------------------

    public static function ensureBankAccount(string $playerId): array
    {
        $db = Database::connection();
        $row = $db->query('SELECT * FROM bank_accounts WHERE user_id = ?', [$playerId])->fetch();
        if ($row !== false) {
            return $row;
        }
        $id = \StellarDominion\Core\UUID::v4();
        $db->prepare(
            'INSERT INTO bank_accounts (id, user_id, account_type, account_balance, interest_rate)
             VALUES (?, ?, ?, 0, ?)'
        )->execute([$id, $playerId, 'standard', (float) Config::get('game.economy.bank_interest_rate', 0.05)]);
        return $db->query('SELECT * FROM bank_accounts WHERE user_id = ?', [$playerId])->fetch();
    }

    public static function deposit(string $playerId, int $amount): array
    {
        if ($amount <= 0) {
            throw new \RuntimeException('Deposit must be positive.');
        }
        $db = Database::connection();
        $account = self::ensureBankAccount($playerId);
        $maxDeposit = (int) Config::get('game.economy.bank_max_deposit', 10_000_000);
        if ($amount > $maxDeposit) {
            throw new \RuntimeException('Deposit exceeds limit.');
        }

        $row = $db->query('SELECT resources FROM player_states WHERE id = ?', [$playerId])->fetch();
        $resources = $row['resources'] ? json_decode($row['resources'], true) : [];
        $credits = (int) ($resources['credits'] ?? 0);
        if ($credits < $amount) {
            throw new \RuntimeException('Insufficient credits.');
        }

        $newBalance = (int) $account['account_balance'] + $amount;
        $db->prepare('UPDATE player_states SET resources = JSON_SET(resources, "$.credits", GREATEST(0, COALESCE(JSON_EXTRACT(resources, "$.credits"), 0) - ?)), updated_at = NOW() WHERE id = ?')
            ->execute([$amount, $playerId]);
        $db->prepare('UPDATE bank_accounts SET account_balance = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$newBalance, $account['id']]);
        $db->prepare(
            'INSERT INTO bank_transactions (id, account_id, transaction_type, amount, balance_before, balance_after)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([\StellarDominion\Core\UUID::v4(), $account['id'], 'deposit', $amount, $account['account_balance'], $newBalance]);

        return ['balance' => $newBalance];
    }

    public static function withdraw(string $playerId, int $amount): array
    {
        if ($amount <= 0) {
            throw new \RuntimeException('Withdrawal must be positive.');
        }
        $db = Database::connection();
        $account = self::ensureBankAccount($playerId);
        $maxWithdrawal = (int) Config::get('game.economy.bank_max_withdrawal', 1_000_000);
        if ($amount > $maxWithdrawal) {
            throw new \RuntimeException('Withdrawal exceeds limit.');
        }
        if ((int) $account['account_balance'] < $amount) {
            throw new \RuntimeException('Insufficient balance.');
        }

        $newBalance = (int) $account['account_balance'] - $amount;
        $db->prepare('UPDATE bank_accounts SET account_balance = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$newBalance, $account['id']]);
        $db->prepare('UPDATE player_states SET resources = JSON_SET(resources, "$.credits", COALESCE(JSON_EXTRACT(resources, "$.credits"), 0) + ?), updated_at = NOW() WHERE id = ?')
            ->execute([$amount, $playerId]);
        $db->prepare(
            'INSERT INTO bank_transactions (id, account_id, transaction_type, amount, balance_before, balance_after)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([\StellarDominion\Core\UUID::v4(), $account['id'], 'withdrawal', $amount, $account['account_balance'], $newBalance]);

        return ['balance' => $newBalance];
    }

    /** Pay interest on all accounts (run on a slow cron). */
    public static function applyInterest(): int
    {
        $db = Database::connection();
        $accounts = $db->query('SELECT * FROM bank_accounts WHERE is_active = 1')->fetchAll();
        $updated = 0;
        foreach ($accounts as $account) {
            $rate = (float) $account['interest_rate'];
            $interest = (int) floor((int) $account['account_balance'] * $rate);
            if ($interest <= 0) {
                continue;
            }
            $newBalance = (int) $account['account_balance'] + $interest;
            $db->prepare('UPDATE bank_accounts SET account_balance = ?, last_interest_payment = NOW(), total_interest_earned = total_interest_earned + ?, updated_at = NOW() WHERE id = ?')
                ->execute([$newBalance, $interest, $account['id']]);
            $db->prepare(
                'INSERT INTO bank_transactions (id, account_id, transaction_type, amount, balance_before, balance_after)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([\StellarDominion\Core\UUID::v4(), $account['id'], 'interest', $interest, $account['account_balance'], $newBalance]);
            $updated++;
        }
        return $updated;
    }
}
