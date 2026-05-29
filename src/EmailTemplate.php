<?php

/**
 * EmailTemplate - branded OilCorp HTML email wrapper.
 * PL: EmailTemplate - szablon brandowanych maili HTML OilCorp.
 *
 * Usage:
 * PL: Uzycie:
 * $html = EmailTemplate::build('Tytul', 'Czesc Jan,', 'Tresc...',
 * 'POTWIERDZ E-MAIL', 'https://...');
 * Mailer::send($to, 'Subject', $html);
 */
class EmailTemplate
{
    private const GOLD  = '#c8a84b';
    private const DARK  = '#0d0d1a';
    private const CARD  = '#13131f';
    private const TEXT  = '#c8c8d4';
    private const MUTED = '#7a7a99';

 /**
 * Build a full branded email.
 * PL: Buduje pelny brandowany email.
 *
 * @param string $title Heading inside email
 * @param string $greeting Greeting line
 * @param string $bodyHtml Main body with HTML allowed
 * @param string|null $btnLabel CTA button label (null = no button)
 * @param string|null $btnUrl CTA button URL
 * @param string $footer Footer text (default: generic disclaimer)
 */
    public static function build(
        string $title,
        string $greeting,
        string $bodyHtml,
        ?string $btnLabel = null,
        ?string $btnUrl = null,
        string $footer = ''
    ): string {
        if ($footer === '') {
            $footer = t('email_template.default_footer');
        }

        $btn = '';
        if ($btnLabel && $btnUrl) {
            $btnUrl = htmlspecialchars($btnUrl, ENT_QUOTES);
            $btnLabel = htmlspecialchars($btnLabel);
            $gold = self::GOLD;
            $btnHint = t('email_template.btn_fallback_hint');
            $btn = <<<HTML
            <p style="text-align:center;margin:28px 0 8px">
                <a href="{$btnUrl}"
                   style="display:inline-block;background:{$gold};color:#0d0d1a;font-weight:700;
                          font-size:15px;letter-spacing:.06em;text-decoration:none;
                          padding:14px 36px;border-radius:6px;">
                    {$btnLabel}
                </a>
            </p>
            <p style="text-align:center;font-size:12px;color:#7a7a99;word-break:break-all;margin-top:8px">
                {$btnHint}<br>
                <a href="{$btnUrl}" style="color:{$gold}">{$btnUrl}</a>
            </p>
HTML;
        }

        $dark  = self::DARK;
        $card  = self::CARD;
        $gold  = self::GOLD;
        $text  = self::TEXT;
        $muted = self::MUTED;
        $subtitle = t('email_template.brand_subtitle');
        $brandIcon = '&#9973;';

        return <<<HTML
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title>
</head>
<body style="margin:0;padding:0;background:{$dark};font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:{$dark};padding:40px 16px;">
<tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;
           background:{$card};border-radius:10px;border:1px solid rgba(200,168,75,.2);
           overflow:hidden;">
        <tr>
            <td style="background:rgba(200,168,75,.08);border-bottom:1px solid rgba(200,168,75,.2);
                       padding:24px 32px;text-align:center;">
                <p style="margin:0;font-size:22px;font-weight:700;color:{$gold};letter-spacing:.06em">
                    {$brandIcon} OilCorp
                </p>
                <p style="margin:6px 0 0;font-size:12px;color:{$muted};letter-spacing:.1em;text-transform:uppercase">
                    {$subtitle}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:32px 32px 24px;color:{$text};font-size:15px;line-height:1.7;">
                <h2 style="margin:0 0 16px;font-size:20px;color:{$gold}">{$title}</h2>
                <p style="margin:0 0 12px">{$greeting}</p>
                <div style="color:{$text}">{$bodyHtml}</div>
                {$btn}
            </td>
        </tr>
`        <tr>
            <td style="padding:20px 32px;border-top:1px solid rgba(255,255,255,.06);
                       font-size:12px;color:{$muted};text-align:center;line-height:1.6;">
                {$footer}
            </td>
        </tr>
    </table>
</td></tr>
</table>
</body>
</html>
HTML;
    }
}
