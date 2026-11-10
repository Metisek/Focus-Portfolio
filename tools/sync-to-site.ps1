<#
.SYNOPSIS
  Copies the plugin from this repository into the FTP mirror of the site.
.DESCRIPTION
  Without -Apply it only lists what would change (nothing is written).
  With -Apply the target becomes an exact copy of the repository: files that
  are not in the repository are removed from the target. Development files
  listed in .distignore are never copied.
.EXAMPLE
  .\tools\sync-to-site.ps1
  .\tools\sync-to-site.ps1 -Apply
  .\tools\sync-to-site.ps1 -Apply -Target "D:\other-site\wp-content\plugins\focus-portfolio"
#>
param(
    [string]$Target = (Join-Path $PSScriptRoot '..\..\KFF_Focus_FTP\wp-content\plugins\focus-portfolio'),
    [switch]$Apply
)

$repo = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$Target = [System.IO.Path]::GetFullPath($Target)

if (-not (Test-Path (Join-Path $repo 'focus-portfolio.php'))) {
    Write-Error "Not a plugin folder: $repo"; exit 1
}
if (-not (Test-Path (Split-Path $Target -Parent))) {
    Write-Error "Target parent folder does not exist: $(Split-Path $Target -Parent)"; exit 1
}

# same exclusion list as the zip and the GitHub release (.distignore)
$rules = Get-Content (Join-Path $repo '.distignore') -Encoding UTF8 | ForEach-Object { $_.Trim() } | Where-Object { $_ -and -not $_.StartsWith('#') }
$excludeDirs  = @($rules | Where-Object { $_.EndsWith('/') } | ForEach-Object { $_.TrimEnd('/') })
$excludeFiles = @($rules | Where-Object { -not $_.EndsWith('/') })

$rcArgs = @($repo, $Target, '/MIR', '/FFT', '/NJH', '/NJS', '/NDL', '/NP', '/XD') + $excludeDirs + @('/XF') + $excludeFiles
if (-not $Apply) { $rcArgs += '/L' }

Write-Host ("From: " + $repo)
Write-Host ("To:   " + $Target)
if (-not $Apply) { Write-Host "Preview only (add -Apply to copy):" -ForegroundColor Yellow }

robocopy @rcArgs
$code = $LASTEXITCODE
# robocopy: 0 = nothing to do, 1-7 = copied / extra files, 8+ = error
if ($code -ge 8) { Write-Error "robocopy failed with code $code"; exit $code }
if ($code -eq 0) { Write-Host "No differences." -ForegroundColor Green }
elseif ($Apply) { Write-Host "Done. Now upload the changed files to the server (VS Code SFTP)." -ForegroundColor Green }
exit 0
