<#
.SYNOPSIS
  Builds an installable plugin zip: dist\focus-portfolio-<version>.zip
.DESCRIPTION
  The zip contains a single folder "focus-portfolio/" with forward-slash paths,
  so WordPress on Linux unpacks it correctly (Compress-Archive in Windows
  PowerShell 5.1 writes backslashes, which breaks there). Development files
  (git, tools, README, CHANGELOG, dist) are left out.
#>
$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

$header = Get-Content (Join-Path $repo 'focus-portfolio.php') -TotalCount 15 -Encoding UTF8
$version = ($header | Select-String -Pattern '^\s*\*\s*Version:\s*(\S+)').Matches[0].Groups[1].Value
if (-not $version) { $version = 'dev' }

$dist = Join-Path $repo 'dist'
New-Item -ItemType Directory -Force $dist | Out-Null
$out = Join-Path $dist ("focus-portfolio-" + $version + ".zip")
if (Test-Path $out) { Remove-Item $out -Force }

$skipDirs  = @('.git', '.vscode', 'tools', 'dist')
$skipFiles = @('.gitignore', '.gitattributes', 'README.md', 'CHANGELOG.md')

Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($out, 'Create')
$count = 0
try {
    Get-ChildItem -Path $repo -Recurse -File | ForEach-Object {
        $rel = $_.FullName.Substring($repo.Length).TrimStart('\', '/')
        $top = $rel.Split('\')[0]
        if ($skipDirs -contains $top) { return }
        if ($skipFiles -contains $rel) { return }
        if ($_.Extension -in @('.tmp', '.zip')) { return }
        $name = 'focus-portfolio/' + $rel.Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $name, 'Optimal') | Out-Null
        $count++
    }
}
finally {
    $zip.Dispose()
}

$size = [math]::Round((Get-Item $out).Length / 1KB)
Write-Host ("{0} files, {1} KB -> {2}" -f $count, $size, $out) -ForegroundColor Green
