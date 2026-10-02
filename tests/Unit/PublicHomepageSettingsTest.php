<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class PublicHomepageSettingsTest extends TestCase
{
    private PDO $db;

    protected function setUp(): void
    {
        $settingsFile = dirname(__DIR__, 2) . '/src/PublicHomepageSettings.php';
        self::assertFileExists($settingsFile);
        require_once $settingsFile;

        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->exec('CREATE TABLE site_config (`key` TEXT PRIMARY KEY, `value` TEXT NOT NULL)');
    }

    public function testHomepageIsEnabledByDefaultWhenSettingIsMissing(): void
    {
        self::assertTrue(PublicHomepageSettings::isEnabled($this->db));
    }

    public function testHomepageCanBeDisabledAndEnabledFromStoredSetting(): void
    {
        $stmt = $this->db->prepare('INSERT INTO site_config (`key`, `value`) VALUES (?, ?)');
        $stmt->execute([PublicHomepageSettings::KEY, '0']);
        self::assertFalse(PublicHomepageSettings::isEnabled($this->db));

        $this->db->prepare('UPDATE site_config SET `value` = ? WHERE `key` = ?')
            ->execute(['1', PublicHomepageSettings::KEY]);
        self::assertTrue(PublicHomepageSettings::isEnabled($this->db));
    }

    public function testHomepageStaysAvailableWhenConfigurationTableIsUnavailable(): void
    {
        $db = new PDO('sqlite::memory:');
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        self::assertTrue(PublicHomepageSettings::isEnabled($db));
    }
}
