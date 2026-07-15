<?php
declare(strict_types=1);

final class EmployeeRef
{
    public const SOURCE_BOARD_MEMBER = 'board_member';
    public const SOURCE_TECHNICAL_STAFF = 'technical_staff';

    public readonly string $sourceType;
    public readonly int $sourceId;
    public readonly int $playerId;

    public function __construct(string $sourceType, int $sourceId, int $playerId)
    {
        if (!in_array($sourceType, self::sourceTypes(), true)) {
            throw new InvalidArgumentException('Unsupported employee source type.');
        }
        if ($sourceId <= 0 || $playerId <= 0) {
            throw new InvalidArgumentException('Employee and player identifiers must be positive.');
        }

        $this->sourceType = $sourceType;
        $this->sourceId = $sourceId;
        $this->playerId = $playerId;
    }

    /** @return list<string> */
    public static function sourceTypes(): array
    {
        return [self::SOURCE_BOARD_MEMBER, self::SOURCE_TECHNICAL_STAFF];
    }

    public function key(): string
    {
        return $this->sourceType . ':' . $this->sourceId;
    }
}
