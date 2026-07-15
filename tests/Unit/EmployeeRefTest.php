<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/src/Employee/EmployeeRef.php';
require_once __DIR__ . '/BaseTestCase.php';

final class EmployeeRefTest extends BaseTestCase
{
    public function testCreatesStableReferenceKey(): void
    {
        $ref = new EmployeeRef(EmployeeRef::SOURCE_BOARD_MEMBER, 17, 4);

        $this->assertSame('board_member:17', $ref->key());
        $this->assertSame(4, $ref->playerId);
    }

    /** @dataProvider invalidReferences */
    public function testRejectsInvalidReference(string $sourceType, int $sourceId, int $playerId): void
    {
        $this->expectException(InvalidArgumentException::class);

        new EmployeeRef($sourceType, $sourceId, $playerId);
    }

    /** @return iterable<string, array{string, int, int}> */
    public function invalidReferences(): iterable
    {
        yield 'unknown source' => ['candidate', 1, 1];
        yield 'zero source id' => [EmployeeRef::SOURCE_BOARD_MEMBER, 0, 1];
        yield 'negative player id' => [EmployeeRef::SOURCE_TECHNICAL_STAFF, 1, -1];
    }
}
