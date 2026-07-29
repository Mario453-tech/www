<?php
declare(strict_types=1);

final class EmployeeDeadlockRetry
{
    /**
     * Retry a complete top-level HR operation after transient database conflicts.
     * Ponawia kompletna operacje HR po przejsciowym konflikcie bazy.
     *
     * @template T
     * @param callable():T $operation
     * @return T
     */
    public static function run(PDO $db, callable $operation, int $maxAttempts = 3): mixed
    {
        $maxAttempts = max(1, min(5, $maxAttempts));
        for ($attempt = 1; ; $attempt++) {
            try {
                return $operation();
            } catch (PDOException $exception) {
                if ($attempt >= $maxAttempts || $db->inTransaction() || !self::isRetryable($exception)) {
                    throw $exception;
                }
                usleep(20000 * $attempt);
            }
        }
    }

    private static function isRetryable(PDOException $exception): bool
    {
        $sqlState = (string)$exception->getCode();
        $driverCode = (int)($exception->errorInfo[1] ?? 0);
        return $sqlState === '40001' || in_array($driverCode, [1205, 1213], true);
    }
}
