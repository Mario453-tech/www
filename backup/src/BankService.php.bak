<?php

require_once __DIR__ . '/Bank/CalculationTrait.php';
require_once __DIR__ . '/Bank/ApplicationTrait.php';
require_once __DIR__ . '/Bank/RepaymentTrait.php';

/**
 * BankService facade for player loan flow.
 * PL: Fasada obslugi kredytow gracza.
 *
 * Logic is split into traits in src/Bank/.
 * PL: Logika jest podzielona na traity w src/Bank/.
 *   - CalculationTrait.php  - annuity and credit limit calculations
 *      PL: obliczenia rat i limitu kredytowego
 *   - ApplicationTrait.php  - application submission and offer lifecycle
 *      PL: skladanie wniosku i obsluga oferty
 *   - RepaymentTrait.php    - repayment flow and active loans
 *      PL: splaty i aktywne kredyty
 */
class BankService
{
    use BankCalculationTrait;
    use BankApplicationTrait;
    use BankRepaymentTrait;

    private PDO $db;

    public function __construct()
    {
        try {
            $this->db = Database::getInstance()->getConnection();
            GameLog::info('BankService', 'Service initialized');
        } catch (Throwable $e) {
            GameLog::error('BankService', 'Initialization failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
