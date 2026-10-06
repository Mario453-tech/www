<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/src/EmailTemplate.php';

final class PasswordResetEmailTest extends TestCase
{
    public function testResetEmailUsesTranslatedSubjectAndRendersSafeHtml(): void
    {
        $_SESSION['locale'] = 'pl';
        $safeName = htmlspecialchars('Jan <Admin>', ENT_QUOTES, 'UTF-8');
        $html = EmailTemplate::build(
            tPlain('auth.reset_email_title'),
            tPlain('auth.reset_email_greeting', ['name' => $safeName]),
            '<p>' . tPlain('auth.reset_email_body') . '</p>',
            tPlain('auth.reset_email_button'),
            'https://oilempire.pl/reset-password?token=test',
            tPlain('auth.reset_email_footer')
        );

        self::assertSame('[OilEmpire] Reset hasła', tPlain('auth.reset_email_subject'));
        self::assertStringContainsString('<strong>Jan &lt;Admin&gt;</strong>', $html);
        self::assertStringNotContainsString('&lt;strong&gt;', $html);
        self::assertStringContainsString('OilEmpire', $html);
        self::assertStringNotContainsString('OilCorp', $html);
    }

    public function testEverySupportedLocaleDefinesResetSubject(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['pl', 'en', 'de'] as $locale) {
            $translations = require $root . '/lang/' . $locale . '/auth.php';
            self::assertArrayHasKey('auth.reset_email_subject', $translations);
            self::assertStringContainsString('OilEmpire', $translations['auth.reset_email_subject']);
        }
    }
}
