<#
.SYNOPSIS
  Builds an installable plugin zip: dist\focus-portfolio-<version>.zip
.DESCRIPTION
  The zip contains a single folder "focus-portfolio/" with forward-slash paths,
  so WordPress on Linux unpacks it correctly (Compress-Archive in Windows
  PowerShell 5.1 writes backslashes, which breaks there). Files listed in
  .distignore are left out (the same list the GitHub release workflow uses).
#>
$ErrorActionPreference = 'Stop'
$repo = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path

$header = Get-Content (Join-Path $repo 'focus-portfolio.php') -TotalCount 15 -Encoding UTF8
$version = ($header | Select-String -Pattern '^\s*\*\s*Version:\s*(\S+)').Matches[0].Groups[1].Value
if (-not $version) { $version = 'dev' }

$rules = Get-Content (Join-Path $repo '.distignore') -Encoding UTF8 | ForEach-Object { $_.Trim() } | Where-Object { $_ -and -not $_.StartsWith('#') }
$skipDirs  = @($rules | Where-Object { $_.EndsWith('/') } | ForEach-Object { $_.TrimEnd('/') })
$skipFiles = @($rules | Where-Object { -not $_.EndsWith('/') })

$dist = Join-Path $repo 'dist'
New-Item -ItemType Directory -Force $dist | Out-Null
$out = Join-Path $dist ("focus-portfolio-" + $version + ".zip")
if (Test-Path $out) { Remove-Item $out -Force }

Add-Type -AssemblyName System.IO.Compression, System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($out, 'Create')
$count = 0
try {
    Get-ChildItem -Path $repo -Recurse -File -Force | ForEach-Object {
        $rel = $_.FullName.Substring($repo.Length).TrimStart('\', '/')
        $parts = $rel.Split('\')
        $dirs = @()
        if ($parts.Length -gt 1) { $dirs = $parts[0..($parts.Length - 2)] }
        foreach ($d in $dirs) { foreach ($p in $skipDirs) { if ($d -like $p) { return } } }
        foreach ($p in $skipFiles) { if ($parts[-1] -like $p) { return } }
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
