<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/CSRF.php';

final class CSRFTest extends BaseTestCase
{
    public function testGenerateTokenPersistsInSession(): void
    {
        $token = CSRF::generateToken();

        $this->assertSame($token, $_SESSION['csrf_token']);
        $this->assertSame(64, strlen($token));
    }

    public function testValidateTokenAcceptsMatchingToken(): void
    {
        $token = CSRF::generateToken();

        $this->assertTrue(CSRF::validateToken($token));
    }

    public function testValidateTokenRejectsWrongToken(): void
    {
        CSRF::generateToken();

        $this->assertFalse(CSRF::validateToken('niepoprawny-token'));
    }

    public function testFieldRendersHiddenInputWithToken(): void
    {
        $field = CSRF::field();

        $this->assertStringContainsString('type="hidden"', $field);
        $this->assertStringContainsString('name="csrf_token"', $field);
        $this->assertStringContainsString((string) $_SESSION['csrf_token'], $field);
    }
}
