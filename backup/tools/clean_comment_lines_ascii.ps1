$root = 'C:\xampp1\www'
$exclude = '\\(backup|backups|vendor|node_modules|\.git|uploads|storage|cache|serwer|assets\\css\\_backup_|C:\\\\xampp1\\\\www\\\\www\\\\|src\\PHPMailer|lang\\)'
$exts = @('.php','.js','.css')
$charMap = @{}
$charMap[[char]0x0105] = 'a'; $charMap[[char]0x0107] = 'c'; $charMap[[char]0x0119] = 'e'; $charMap[[char]0x0142] = 'l'; $charMap[[char]0x0144] = 'n'; $charMap[[char]0x00F3] = 'o'; $charMap[[char]0x015B] = 's'; $charMap[[char]0x017A] = 'z'; $charMap[[char]0x017C] = 'z'
$charMap[[char]0x0104] = 'A'; $charMap[[char]0x0106] = 'C'; $charMap[[char]0x0118] = 'E'; $charMap[[char]0x0141] = 'L'; $charMap[[char]0x0143] = 'N'; $charMap[[char]0x00D3] = 'O'; $charMap[[char]0x015A] = 'S'; $charMap[[char]0x0179] = 'Z'; $charMap[[char]0x017B] = 'Z'
$charMap[[char]0x2013] = '-'; $charMap[[char]0x2014] = '-'; $charMap[[char]0x2026] = '...'; $charMap[[char]0x201E] = '"'; $charMap[[char]0x201D] = '"'; $charMap[[char]0x201C] = '"'; $charMap[[char]0x2019] = "'"; $charMap[[char]0x2018] = "'"; $charMap[[char]0x00B7] = '-'
$charMap[[char]0xFFFD] = ''; $charMap[[char]0x00C2] = ''; $charMap[[char]0x0102] = ''; $charMap[[char]0x00C4] = ''; $charMap[[char]0x00C5] = ''; $charMap[[char]0x0139] = ''; $charMap[[char]0x00E2] = ''; $charMap[[char]0x0111] = ''; $charMap[[char]0x0161] = 's'; $charMap[[char]0x0165] = 't'; $charMap[[char]0x0164] = 'T'; $charMap[[char]0x20AC] = ''; $charMap[[char]0x2122] = ''; $charMap[[char]0x0153] = 'oe'; $charMap[[char]0x02C7] = ''; $charMap[[char]0x00B8] = ''
function Norm([string]$s) { $sb=New-Object System.Text.StringBuilder; foreach($ch in $s.ToCharArray()){ if($script:charMap.ContainsKey($ch)){ [void]$sb.Append($script:charMap[$ch]) } else { [void]$sb.Append($ch) } }; return ($sb.ToString() -replace '\s{2,}',' ') }
function Backup([string]$path){ $rel=$path.Substring($root.Length).TrimStart('\'); $dest=Join-Path (Join-Path $root 'backup') ($rel+'.bak'); $dir=Split-Path $dest -Parent; if(!(Test-Path $dir)){ New-Item -ItemType Directory -Path $dir -Force | Out-Null }; Copy-Item -LiteralPath $path -Destination $dest -Force }
$utf8NoBom=New-Object System.Text.UTF8Encoding($false)
$changed=@()
Get-ChildItem $root -Recurse -File | Where-Object { $exts -contains $_.Extension.ToLower() -and $_.FullName -notmatch $exclude } | ForEach-Object {
  $path=$_.FullName
  $lines=[System.IO.File]::ReadAllLines($path,[System.Text.Encoding]::UTF8)
  $new=[string[]]::new($lines.Length)
  $dirty=$false
  for($i=0;$i -lt $lines.Length;$i++){
    $line=$lines[$i]
    $trim=$line.TrimStart()
    $out=$line
    if($trim.StartsWith('//') -or $trim.StartsWith('#') -or $trim.StartsWith('*') -or $trim.StartsWith('/*')){
      $out=Norm $line
    } elseif($line.Contains('/*') -and $line.Contains('*/')) {
      $start=$line.IndexOf('/*'); $end=$line.IndexOf('*/',$start+2)
      if($end -gt $start){ $out=$line.Substring(0,$start)+(Norm $line.Substring($start,$end-$start+2))+$line.Substring($end+2) }
    }
    if($out -ne $line){ $dirty=$true }
    $new[$i]=$out
  }
  if($dirty){ Backup $path; [System.IO.File]::WriteAllLines($path,$new,$utf8NoBom); $changed += $path }
}
'CHANGED='+$changed.Count
$changed | Select-Object -First 200