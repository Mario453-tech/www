<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Security.php';

final class SecurityHttpsRequestTest extends BaseTestCase
{
    public function testReturnsFalseWhenPhpMarksHttpRequestAsOff(): void
    {
        $this->assertFalse(Security::isHttpsRequest(['HTTPS' => 'off', 'SERVER_PORT' => '80']));
    }

    public function testReturnsTrueForNativeHttpsRequest(): void
    {
        $this->assertTrue(Security::isHttpsRequest(['HTTPS' => 'on', 'SERVER_PORT' => '443']));
    }

    public function testReturnsTrueForTrustedProxyHttpsRequest(): void
    {
        $this->assertTrue(Security::isHttpsRequest(['HTTP_X_FORWARDED_PROTO' => 'https']));
    }

    public function testUsesFirstForwardedProtocolValue(): void
    {
        $this->assertTrue(Security::isHttpsRequest(['HTTP_X_FORWARDED_PROTO' => 'https, http']));
    }
}
