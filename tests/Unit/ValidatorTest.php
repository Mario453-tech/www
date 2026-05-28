<?php
declare(strict_types=1);

require_once __DIR__ . '/BaseTestCase.php';
require_once dirname(__DIR__, 2) . '/src/Validator.php';

final class ValidatorTest extends BaseTestCase
{
    public function testSanitizeTrimsAndEscapesHtml(): void
    {
        $result = Validator::sanitize("  <b>Oil & Gas</b>  ");

        $this->assertSame('&lt;b&gt;Oil &amp; Gas&lt;/b&gt;', $result);
    }

    public function testValidateEmailAcceptsValidAddress(): void
    {
        $this->assertTrue(Validator::validateEmail('gracz@example.com'));
    }

    public function testValidateEmailRejectsInvalidAddress(): void
    {
        $this->assertFalse(Validator::validateEmail('zly-email'));
    }

    public function testValidateIntHonorsBounds(): void
    {
        $this->assertTrue(Validator::validateInt('10', 1, 20));
        $this->assertFalse(Validator::validateInt('0', 1, 20));
        $this->assertFalse(Validator::validateInt('21', 1, 20));
    }

    public function testValidateFloatHonorsMinimum(): void
    {
        $this->assertTrue(Validator::validateFloat('12.5', 10.0));
        $this->assertFalse(Validator::validateFloat('9.99', 10.0));
        $this->assertFalse(Validator::validateFloat('abc', 0.0));
    }
}
