<?php
declare(strict_types=1);

function ttsTestService(PDO $db, int $playerId): TechnicalTeamService
{
    $service = new class extends TechnicalTeamService {
        public function __construct() {}
        public function getManager(): ?array { return null; }
        protected function getTechnicalStrikeEffect(): array { return []; }
    };
    (new ReflectionProperty(TechnicalTeamService::class, 'db'))->setValue($service, $db);
    (new ReflectionProperty(TechnicalTeamService::class, 'playerId'))->setValue($service, $playerId);
    return $service;
}
