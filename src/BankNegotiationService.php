<?php

require_once __DIR__ . '/BankNegotiation/ContextTrait.php';
require_once __DIR__ . '/BankNegotiation/MessagesTrait.php';
require_once __DIR__ . '/BankNegotiation/RandomEventsTrait.php';
require_once __DIR__ . '/BankNegotiation/RequestsTrait.php';
require_once __DIR__ . '/BankNegotiation/ProcessorTrait.php';

/**
 * BankNegotiationService v3.
 * PL: Glowny serwis negocjacji bankowych.
 *
 * Negotiation types (max 3).
 * PL: Typy negocjacji (max 3).
 * 1. deferral - repayment deferral (30/90/180 days)
 * PL: odroczenie splaty (30/90/180 dni)
 * 2. restructure - schedule restructuring
 * PL: restrukturyzacja harmonogramu
 * 3. recovery - recovery plan that suspends bailiff until full repayment
 * PL: plan naprawczy zawieszajacy komornika do pelnej splaty
 *
 * Trust score is an internal bank parameter (0-100) hidden from the player.
 * PL: Trust score to wewnetrzny parametr banku (0-100), niewidoczny dla gracza.
 * It is stored in bank_trust_scores and visible only to GM/admin.
 * PL: Parametr jest zapisywany w bank_trust_scores i widoczny tylko dla GM/admina.
 *
 * Dynamic fee formula:
 * PL: Formula dynamicznej prowizji:
 * fee = remaining x base_rate x all factors (LTV, market, wells, CFO, trust)
 * PL: fee = remaining x base_rate x wszystkie czynniki (LTV, rynek, odwierty, CFO, trust)
 * cap: min 0.5%, max 14%
 * PL: limit: min 0.5%, max 14%
 *
 * Decision time is built from base hours and extensions.
 * PL: Czas decyzji sklada sie z bazowych godzin i przedluzen.
 * CFO can reduce the duration based on skill.
 * PL: CFO moze skrocic czas zalezne od swojego skilla.
 *
 * Recovery plan suspends the bailiff until full repayment or breach.
 * PL: Plan naprawczy zawiesza komornika do czasu splaty lub naruszenia.
 * A missed installment immediately reactivates the bailiff.
 * PL: Opozniona rata natychmiast przywraca komornika.
 *
 * Logic is split into traits in src/BankNegotiation/.
 * PL: Logika jest podzielona na traity w src/BankNegotiation/.
 * - ContextTrait.php - buildContext, trust score, time and fee calculations
 * PL: buildContext, trust score, obliczenia czasu i prowizji
 * - MessagesTrait.php - opening and decision messages
 * PL: wiadomosci otwierajace i decyzyjne
 * - RandomEventsTrait.php - triggerRandomEvent
 * PL: triggerRandomEvent
 * - RequestsTrait.php - negotiation request entry points
 * PL: glowne wejscia dla wnioskow negocjacyjnych
 * - ProcessorTrait.php - processing, applying, querying and helpers
 * PL: przetwarzanie, zatwierdzanie, odczyty i helpery
 */
class BankNegotiationService
{
    use BankNegotiationContextTrait;
    use BankNegotiationMessagesTrait;
    use BankNegotiationRandomEventsTrait;
    use BankNegotiationRequestsTrait;
    use BankNegotiationProcessorTrait;

 // Base fee rates per type.
 // PL: Bazowe stawki prowizji per typ.
    const BASE_FEE = [
        'deferral_30'  => 0.020,
        'deferral_90'  => 0.040,
        'deferral_180' => 0.070,
    ];

    const DEFERRAL_RATE_INCREASE = [
        30  => 5.0,
        90  => 10.0,
        180 => 15.0,
    ];

    const RESTRUCTURE_BASE_FEE_PCT    = 0.030;
    const RESTRUCTURE_MONTHLY_FEE_PCT = 0.020;
    const RECOVERY_BASE_FEE_PCT       = 0.025;

    const APPROVAL_VALID_HOURS = 48;

 // Trust score change per event, stored for DB and GM/admin review.
 // PL: Zmiana trust score per zdarzenie, zapisywana do DB i panelu GM/admin.
    const TRUST = [
        'rata_na_czas'          => +5,
        'negocjacja_sukces'     => +8,
        'negocjacja_dotrzymana' => +3,
        'plan_naprawczy_ok'     => +10,
        'wczesniejsza_splata'   => +5,
        'opoznienie_3_ticki'    => -10,
        'komornik_aktywny'      => -20,
        'negocjacja_odrzucona'  => -15,
        'bankructwo'            => -25,
        'zbyt_czesto_wniosek'   => -8,
        'plan_naruszony'        => -20,
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }
}
