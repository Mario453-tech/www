<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GameShellHeadingsTest extends TestCase
{
    public function testDepartmentHeadingsArePlainAndDoNotRepeatBrand(): void
    {
        $titles = [
            'finance' => 'finance.page_title',
            'logistics' => 'logistics.page_title',
            'bank' => 'bank.title',
            'market' => 'market.page_title',
            'hr' => 'hr.page_title',
            'board' => 'boardroom.page_title',
            'map' => 'map.page_title',
            'director' => 'dashboard.page_title',
        ];

        foreach (['pl', 'en'] as $locale) {
            foreach ($titles as $file => $key) {
                $translations = require dirname(__DIR__, 2) . "/lang/{$locale}/{$file}.php";
                $this->assertArrayHasKey($key, $translations);
                $this->assertNotSame('', trim($translations[$key]));
                $this->assertDoesNotMatchRegularExpression('/Oil\s*Corp|&[a-z]+;|<[^>]+>/i', $translations[$key], "{$locale}:{$key}");
            }
        }
    }
}
