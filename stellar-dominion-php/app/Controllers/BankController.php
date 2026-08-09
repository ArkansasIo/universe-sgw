<?php

declare(strict_types=1);

namespace StellarDominion\Controllers;

use StellarDominion\Core\Database;
use StellarDominion\Core\HttpException;
use StellarDominion\Core\Request;
use StellarDominion\Core\Response;
use StellarDominion\Engine\EconomyEngine;

/**
 * Bank vault endpoints.
 *
 * Port of routes-bank-vault.ts + bankService.
 */
final class BankController extends BaseController
{
    public function account(Request $request): Response
    {
        $user = $this->currentUser($request);
        $account = EconomyEngine::ensureBankAccount($user->id);
        $account['transactions'] = Database::connection()
            ->query('SELECT * FROM bank_transactions WHERE account_id = ? ORDER BY created_at DESC LIMIT 50', [$account['id']])
            ->fetchAll();
        return $this->json($account);
    }

    public function deposit(Request $request): Response
    {
        $user = $this->currentUser($request);
        try {
            $result = EconomyEngine::deposit($user->id, (int) $request->input('amount', 0));
        } catch (\RuntimeException $e) {
            throw HttpException::unprocessable($e->getMessage());
        }
        return $this->json($result, 'Deposited');
    }

    public function withdraw(Request $request): Response
    {
        $user = $this->currentUser($request);
        try {
            $result = EconomyEngine::withdraw($user->id, (int) $request->input('amount', 0));
        } catch (\RuntimeException $e) {
            throw HttpException::unprocessable($e->getMessage());
        }
        return $this->json($result, 'Withdrawn');
    }
}
