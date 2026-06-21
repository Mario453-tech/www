<?php
/**
 * Logika biznesowa podglądu i eksportu zgód.
 */
class ConsentService
{
    public function __construct(
        private readonly ConsentRepository  $repo,
        private readonly PrivacyAuditLogger $audit
    ) {}

    public function exportCsv(array $filters): string
    {
        $result = $this->repo->getList($filters, 1, 10000);
        $rows   = $result['rows'];

        $lines   = [];
        $lines[] = implode(',', ['ID', 'player_id', 'username', 'anonymous_token', 'consent_version',
                                  'banner_version', 'accepted', 'rejected', 'source',
                                  'ip_address', 'created_at', 'withdrawn_at']);
        foreach ($rows as $r) {
            $lines[] = implode(',', array_map(
                fn($v) => '"' . str_replace('"', '""', (string)$v) . '"',
                [
                    $r['id'],
                    $r['player_id']        ?? '',
                    $r['username']         ?? '',
                    $r['anonymous_token'],
                    $r['consent_version'],
                    $r['banner_version'],
                    $r['accepted_categories_json'],
                    $r['rejected_categories_json'],
                    $r['source'],
                    $r['ip_address']       ?? '',
                    $r['created_at'],
                    $r['withdrawn_at']     ?? '',
                ]
            ));
        }
        return implode("\r\n", $lines);
    }
}
