<?php

/**
 * Totp — samodzielna implementacja TOTP (RFC 6238), zgodna z Google Authenticator.
 * Self-contained TOTP (RFC 6238) compatible with Google Authenticator.
 *
 * Bez zaleznosci zewnetrznych (vendor/ nie jest wgrywany na serwer).
 * No external dependencies (vendor/ is not deployed to the server).
 *
 * Uzycie / Usage:
 *   $secret = Totp::generateSecret();
 *   $uri    = Totp::provisioningUri($secret, 'admin@oilempire.pl', 'OilEmpire');
 *   $ok     = Totp::verify($secret, $codeOd-Uzytkownika);
 */
final class Totp
{
    private const PERIOD = 30;     // okno czasowe w sekundach / time step in seconds
    private const DIGITS = 6;      // dlugosc kodu / code length
    private const ALGO   = 'sha1'; // Google Authenticator: SHA1
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Wygeneruj losowy sekret base32 (domyslnie 160 bitow).
     * Generate a random base32 secret (160 bits by default).
     */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /**
     * Zweryfikuj kod z tolerancja +/- $window krokow (dryf zegara).
     * Verify a code allowing +/- $window steps of clock drift.
     */
    public static function verify(string $secret, string $code, int $window = 1, ?int $time = null): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) {
            return false;
        }
        $time = $time ?? time();
        for ($i = -$window; $i <= $window; $i++) {
            $counter = intdiv($time, self::PERIOD) + $i;
            if (hash_equals(self::hotp($secret, $counter), $code)) {
                return true;
            }
        }
        return false;
    }

    /**
     * HOTP dla danego licznika (RFC 4226).
     * HOTP for a given counter (RFC 4226).
     */
    public static function hotp(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        // 8-bajtowy licznik big-endian / 8-byte big-endian counter
        $bin = pack('N', ($counter >> 32) & 0xFFFFFFFF) . pack('N', $counter & 0xFFFFFFFF);
        $hash = hash_hmac(self::ALGO, $bin, $key, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
        $value = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );
        $otp = $value % (10 ** self::DIGITS);
        return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * URI otpauth:// do QR / do recznego dodania w aplikacji.
     * otpauth:// URI for QR / manual entry in the authenticator app.
     */
    public static function provisioningUri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer) . ':' . rawurlencode($account);
        $query = http_build_query([
            'secret'    => $secret,
            'issuer'    => $issuer,
            'algorithm' => strtoupper(self::ALGO),
            'digits'    => self::DIGITS,
            'period'    => self::PERIOD,
        ]);
        return "otpauth://totp/{$label}?{$query}";
    }

    /**
     * Sformatuj sekret w grupy po 4 znaki (czytelniejsze do recznego wpisania).
     * Format the secret in groups of 4 (easier to type manually).
     */
    public static function formatSecret(string $secret): string
    {
        return trim(chunk_split($secret, 4, ' '));
    }

    // ── base32 (RFC 4648) ──────────────────────────────────────────────

    public static function base32Encode(string $data): string
    {
        if ($data === '') {
            return '';
        }
        $bits = '';
        foreach (str_split($data) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= self::ALPHABET[bindec($chunk)];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        if ($b32 === '') {
            return '';
        }
        $bits = '';
        foreach (str_split($b32) as $char) {
            $idx = strpos(self::ALPHABET, $char);
            $bits .= str_pad(decbin($idx), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
