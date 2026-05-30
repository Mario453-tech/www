$root = 'C:\xampp1\www'
$exclude = '\\(backup|backups|vendor|node_modules|\.git|uploads|storage|cache|serwer)\\'
$exts = @('.php','.js','.css')

$charMap = @{}
# Polish diacritics -> ASCII
$charMap[[char]0x0105] = 'a'; $charMap[[char]0x0107] = 'c'; $charMap[[char]0x0119] = 'e'; $charMap[[char]0x0142] = 'l'; $charMap[[char]0x0144] = 'n'; $charMap[[char]0x00F3] = 'o'; $charMap[[char]0x015B] = 's'; $charMap[[char]0x017A] = 'z'; $charMap[[char]0x017C] = 'z'
$charMap[[char]0x0104] = 'A'; $charMap[[char]0x0106] = 'C'; $charMap[[char]0x0118] = 'E'; $charMap[[char]0x0141] = 'L'; $charMap[[char]0x0143] = 'N'; $charMap[[char]0x00D3] = 'O'; $charMap[[char]0x015A] = 'S'; $charMap[[char]0x0179] = 'Z'; $charMap[[char]0x017B] = 'Z'
# Typography and common broken characters -> ASCII or remove
$charMap[[char]0x2013] = '-'; $charMap[[char]0x2014] = '-'; $charMap[[char]0x2026] = '...'; $charMap[[char]0x201E] = '"'; $charMap[[char]0x201D] = '"'; $charMap[[char]0x201C] = '"'; $charMap[[char]0x2019] = "'"; $charMap[[char]0x2018] = "'"; $charMap[[char]0x00B7] = '-'
$charMap[[char]0xFFFD] = ''; $charMap[[char]0x00C2] = ''; $charMap[[char]0x0102] = ''; $charMap[[char]0x00C4] = ''; $charMap[[char]0x00C5] = ''; $charMap[[char]0x0139] = ''; $charMap[[char]0x00E2] = ''; $charMap[[char]0x0111] = ''; $charMap[[char]0x0161] = 's'; $charMap[[char]0x0165] = 't'; $charMap[[char]0x0164] = 'T'; $charMap[[char]0x20AC] = ''; $charMap[[char]0x2122] = ''; $charMap[[char]0x0153] = 'oe'; $charMap[[char]0x02C7] = ''; $charMap[[char]0x00B8] = ''

function Normalize-CommentText([string]$s) {
    $sb = New-Object System.Text.StringBuilder
    foreach ($ch in $s.ToCharArray()) {
        if ($script:charMap.ContainsKey($ch)) { [void]$sb.Append($script:charMap[$ch]) } else { [void]$sb.Append($ch) }
    }
    $s = $sb.ToString()
    $s = $s -replace '\s{2,}', ' '
    return $s
}

function Find-LineCommentStart([string]$line, [string]$ext) {
    $inSingle = $false; $inDouble = $false; $escape = $false
    for ($i=0; $i -lt $line.Length; $i++) {
        $ch = $line[$i]
        if ($escape) { $escape = $false; continue }
        if ($ch -eq '\') { $escape = $true; continue }
        if (-not $inDouble -and $ch -eq "'") { $inSingle = -not $inSingle; continue }
        if (-not $inSingle -and $ch -eq '"') { $inDouble = -not $inDouble; continue }
        if (-not $inSingle -and -not $inDouble) {
            if ($i + 1 -lt $line.Length -and $line[$i] -eq '/' -and $line[$i+1] -eq '/') { return $i }
            if ($ext -eq '.php' -and $line[$i] -eq '#') { return $i }
        }
    }
    return -1
}

function Find-BlockCommentStart([string]$line) {
    $inSingle = $false; $inDouble = $false; $escape = $false
    for ($i=0; $i -lt $line.Length-1; $i++) {
        $ch = $line[$i]
        if ($escape) { $escape = $false; continue }
        if ($ch -eq '\') { $escape = $true; continue }
        if (-not $inDouble -and $ch -eq "'") { $inSingle = -not $inSingle; continue }
        if (-not $inSingle -and $ch -eq '"') { $inDouble = -not $inDouble; continue }
        if (-not $inSingle -and -not $inDouble -and $line[$i] -eq '/' -and $line[$i+1] -eq '*') { return $i }
    }
    return -1
}

function Backup-File([string]$path) {
    $relative = $path.Substring($root.Length).TrimStart('\')
    $dest = Join-Path (Join-Path $root 'backup') ($relative + '.bak')
    $dir = Split-Path $dest -Parent
    if (!(Test-Path $dir)) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    Copy-Item -LiteralPath $path -Destination $dest -Force
}

$utf8NoBom = New-Object System.Text.UTF8Encoding($false)
$changed = @()
$files = Get-ChildItem $root -Recurse -File | Where-Object { $exts -contains $_.Extension.ToLower() -and $_.FullName -notmatch $exclude }
foreach ($f in $files) {
    $bytes = [System.IO.File]::ReadAllBytes($f.FullName)
    $text = [System.Text.Encoding]::UTF8.GetString($bytes)
    $newline = if ($text.Contains("`r`n")) { "`r`n" } else { "`n" }
    $lines = $text -split "`r?`n", -1
    $out = New-Object System.Collections.Generic.List[string]
    $inBlock = $false
    $fileChanged = $false
    foreach ($line in $lines) {
        $newLine = $line
        if ($inBlock) {
            $end = $newLine.IndexOf('*/')
            if ($end -ge 0) {
                $before = $newLine.Substring(0, $end+2)
                $after = $newLine.Substring($end+2)
                $newLine = (Normalize-CommentText $before) + $after
                $inBlock = $false
            } else {
                $newLine = Normalize-CommentText $newLine
            }
        } else {
            $lineStart = Find-LineCommentStart $newLine $f.Extension.ToLower()
            $blockStart = Find-BlockCommentStart $newLine
            if ($blockStart -ge 0 -and ($lineStart -lt 0 -or $blockStart -lt $lineStart)) {
                $end = $newLine.IndexOf('*/', $blockStart+2)
                if ($end -ge 0) {
                    $before = $newLine.Substring(0, $blockStart)
                    $comment = $newLine.Substring($blockStart, $end - $blockStart + 2)
                    $after = $newLine.Substring($end+2)
                    $newLine = $before + (Normalize-CommentText $comment) + $after
                } else {
                    $before = $newLine.Substring(0, $blockStart)
                    $comment = $newLine.Substring($blockStart)
                    $newLine = $before + (Normalize-CommentText $comment)
                    $inBlock = $true
                }
            } elseif ($lineStart -ge 0) {
                $before = $newLine.Substring(0, $lineStart)
                $comment = $newLine.Substring($lineStart)
                $newLine = $before + (Normalize-CommentText $comment)
            }
        }
        if ($newLine -ne $line) { $fileChanged = $true }
        $out.Add($newLine)
    }
    if ($fileChanged) {
        Backup-File $f.FullName
        [System.IO.File]::WriteAllText($f.FullName, ($out -join $newline), $utf8NoBom)
        $changed += $f.FullName
    }
}
'CHANGED='+$changed.Count
$changed | Select-Object -First 200