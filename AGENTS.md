# AGENTS.md

## OilCorp local rules

- Agent responses for this workspace: Polish.
- Code comments in PHP/JS/CSS: English.
- Use `apply_patch` for manual file edits.

## Backup naming

When creating manual backup files, use this exact format:

```text
YYYYMMDD_HHMMSS_original_filename.bak
```

Examples:

```text
20260526_143000_admin.php.bak
20260526_143000_transport.php.bak
20260526_143000_well_grid.js.bak
```

Do not create backup names like:

```text
admin.php.bak_20260526_143000
transport.php.bak_restore_20260526_143000
```

Reason: backup files must keep `.bak` as the final extension so FTP exclusion rules can ignore them reliably.
