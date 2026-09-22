<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
require_once dirname(__DIR__, 2) . '/src/AdminNewsHtml.php';

final class HelpHtmlSanitizerTest extends TestCase
{
    public function testTableSurvivesSaveAndLegacyRenderSanitization(): void
    {
        foreach (['Tabela pomocy', 'Help table'] as $caption) {
            $input = '<table><caption>' . $caption . '</caption><colgroup span="2"><col span="2"></colgroup>'
                . '<thead><tr><th scope="col" abbr="Qty">Quantity</th><th scope="col">Value</th></tr></thead>'
                . '<tbody><tr><th scope="row" rowspan="2">Oil</th><td><strong>10</strong></td></tr>'
                . '<tr><td>20</td></tr></tbody><tfoot><tr><td colspan="2">Total</td></tr></tfoot></table>';
            $saved = AdminNewsHtml::sanitizeContent($input);
            $rendered = AdminNewsHtml::sanitizeContent($saved);
            self::assertSame($saved, $rendered);
            foreach (['table', 'caption', 'colgroup', 'col', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td'] as $tag) {
                self::assertMatchesRegularExpression('/<' . $tag . '(?:\s|>)/', $rendered);
            }
            foreach (['scope="col"', 'scope="row"', 'rowspan="2"', 'colspan="2"', 'span="2"', 'abbr="Qty"', '<strong>10</strong>', $caption] as $fragment) {
                self::assertStringContainsString($fragment, $rendered);
            }
            self::assertSame($saved, AdminNewsHtml::sanitizeContent($input));
        }
    }

    public function testMaliciousTableAttributesAndNestedPayloadsAreRemoved(): void
    {
        $input = '<table onclick="attack()" background="javascript:attack()" id="location" style="position:fixed;background-image:url(javascript:attack());color:red">'
            . '<caption onmouseover="attack()">Safe</caption><colgroup span="-1"><col span="1e3" width="999999"></colgroup>'
            . '<tbody onload="attack()"><tr><th scope="javascript:attack()" colspan="0" rowspan="1001" abbr="&quot; onfocus=&quot;attack()">Header</th>'
            . '<td colspan="2 onclick=attack()" rowspan="-1" formaction="javascript:attack()"><script>attack()</script>'
            . '<iframe src="https://evil.test"></iframe><svg onload="attack()"></svg><a href="javascript:attack()">Safe link</a></td></tr></tbody></table>';
        $safe = AdminNewsHtml::sanitizeContent($input);
        $doc = new DOMDocument();
        @$doc->loadHTML($safe);
        foreach ($doc->getElementsByTagName('*') as $node) {
            foreach ($node->attributes as $attr) {
                self::assertFalse(str_starts_with(strtolower($attr->name), 'on'));
                self::assertContains($attr->name, ['style', 'abbr']);
            }
        }
        foreach (['<script', '<iframe', '<svg', 'background-image', 'position:', 'javascript:', 'id="location"'] as $unsafe) {
            self::assertStringNotContainsString($unsafe, $safe);
        }
        self::assertStringContainsString('<table', $safe);
        self::assertStringContainsString('color: red', $safe);
        self::assertSame($safe, AdminNewsHtml::sanitizeContent($safe));
    }

    public function testTablesAreNotAllowedInTitles(): void
    {
        self::assertStringNotContainsString('<table', AdminNewsHtml::sanitizeTitle('<table><tr><td>Title</td></tr></table>'));
    }

    public function testBothHelpLanguagesAreSanitizedAtSaveAndLegacyRender(): void
    {
        $save = file_get_contents(dirname(__DIR__, 2) . '/admin/help_editor.php');
        self::assertStringContainsString("\$content = AdminNewsHtml::sanitizeContent((string)(\$_POST['content'] ?? ''));", $save);
        self::assertStringContainsString("\$content_en = AdminNewsHtml::sanitizeContent((string)(\$_POST['content_en'] ?? ''));", $save);
        self::assertStringContainsString('AdminNewsHtml::sanitizeContent($displayContent)', file_get_contents(dirname(__DIR__, 2) . '/public/help.php'));
    }
}
