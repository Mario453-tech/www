<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class EnvironmentSmokeTest extends TestCase
{
    public function testPhpUnitEnvironmentIsBootstrapped(): void
    {
        $this->assertSame('testing', getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? null));
        $this->assertTrue(class_exists('Validator'));
        $this->assertTrue(class_exists('TransportConfigService'));
        $this->assertTrue(class_exists('CSRF'));
    }
}
