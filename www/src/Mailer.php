<?php
/**
 * Mailer.php - PHPMailer wrapper.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        try {
            $cfg = require __DIR__ . '/../config/mail.php';
            $mail = new PHPMailer(true);

            $mail->isSMTP();
            $mail->Host = $cfg['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $cfg['username'];
            $mail->Password = $cfg['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = $cfg['port'];
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($cfg['username'], $cfg['from_name']);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $body));

            $mail->send();

            GameLog::info('Mailer', 'Email sent', [
                'to' => $to,
                'subject' => $subject,
            ]);

            return true;
        } catch (Throwable $e) {
            GameLog::error('Mailer', 'Send failed', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
            error_log('Mailer error: ' . $e->getMessage());
            return false;
        }
    }
}
