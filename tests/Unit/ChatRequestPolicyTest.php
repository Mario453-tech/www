<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/src/ChatRequestPolicy.php';

final class ChatRequestPolicyTest extends TestCase
{
    public function testAllMutationsRequireCsrfIncludingLegacyAliases(): void
    {
        foreach (['', 'send', 'send_room', 'send_direct', 'mark_read', 'heartbeat', 'delete_attachment', 'report', 'dm_upload_chunk'] as $action) {
            self::assertSame(419, ChatRequestPolicy::status('POST', $action, true, false));
            self::assertSame(200, ChatRequestPolicy::status('POST', $action, true, true));
            self::assertSame(401, ChatRequestPolicy::status('POST', $action, false, true));
        }
        self::assertSame(405, ChatRequestPolicy::status('GET', 'send_room', true, true));
        self::assertSame(405, ChatRequestPolicy::status('DELETE', '', true, true));
        self::assertSame(200, ChatRequestPolicy::status('GET', 'presence', true, false));
    }

    public function testTechnicalExceptionsAreNeverExposed(): void
    {
        self::assertSame(['key' => 'common.app_error', 'status' => 500], ChatRequestPolicy::error(new PDOException('secret SQL payload')));
        self::assertSame(403, ChatRequestPolicy::error(new RuntimeException('chat.err_room_read_only'))['status']);
        self::assertSame(429, ChatRequestPolicy::error(new RuntimeException('chat.err_rate_limited'))['status']);
    }

    public function testNamedCapabilitiesAreAdminOnly(): void
    {
        self::assertTrue(ChatRequestPolicy::allows('chat.rooms.manage', true));
        self::assertFalse(ChatRequestPolicy::allows('chat.rooms.manage', false));
        self::assertFalse(ChatRequestPolicy::allows('unknown', true));
    }
}
