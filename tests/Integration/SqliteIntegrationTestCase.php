<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/Unit/BaseTestCase.php';

abstract class SqliteIntegrationTestCase extends BaseTestCase
{
    protected function createSqlitePdo(): PDO
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $db->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'), 0);
        $db->sqliteCreateFunction('LEAST', static fn($a, $b) => min((float)$a, (float)$b), 2);
        $db->sqliteCreateFunction('GREATEST', static fn($a, $b) => max((float)$a, (float)$b), 2);

        return $db;
    }

    protected function setPrivateProperty(object $object, string $className, string $property, mixed $value): void
    {
        $ref = new ReflectionProperty($className, $property);
        $ref->setAccessible(true);
        $ref->setValue($object, $value);
    }
}
