$ErrorActionPreference = 'Stop'

$extensionRoot = Join-Path $env:USERPROFILE '.vscode\extensions'
$rootInfo = Get-Item -LiteralPath $extensionRoot -ErrorAction Stop
$rootFullName = $rootInfo.FullName.TrimEnd('\')

$keepPrefixes = @(
    'bmewburn.vscode-intelephense-client',
    'xdebug.php-debug',
    'laravel.vscode-laravel',
    'mohamedbenhida.laravel-intellisense',
    'cweijan.vscode-mysql-client2',
    'cweijan.dbclient-jdbc',
    'damms005.devdb',
    'mtxr.sqltools',
    'mtxr.sqltools-driver-mysql',
    'mtxr.sqltools-driver-sqlite',
    'mtxr.sqltools-driver-pg',
    'qwtel.sqlite-viewer',
    'alexcvzz.vscode-sqlite'
)

$timestamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$reportPath = Join-Path (Get-Location) "vscode-extension-cleanup-$timestamp.txt"

$extensions = Get-ChildItem -LiteralPath $rootFullName -Directory -Force |
    Where-Object { $_.Name -ne '..' -and $_.Name -ne '.' }

$kept = New-Object System.Collections.Generic.List[string]
$removed = New-Object System.Collections.Generic.List[string]

foreach ($extension in $extensions) {
    $extensionFullName = $extension.FullName
    if (-not $extensionFullName.StartsWith($rootFullName + '\', [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to remove path outside extension root: $extensionFullName"
    }

    $shouldKeep = $false
    foreach ($prefix in $keepPrefixes) {
        if ($extension.Name.StartsWith($prefix + '-', [System.StringComparison]::OrdinalIgnoreCase)) {
            $shouldKeep = $true
            break
        }
    }

    if ($shouldKeep) {
        $kept.Add($extension.Name)
        continue
    }

    Remove-Item -LiteralPath $extensionFullName -Recurse -Force
    $removed.Add($extension.Name)
}

@(
    "VS Code extension cleanup: $timestamp"
    "Extension root: $rootFullName"
    ""
    "Kept:"
    ($kept | Sort-Object | ForEach-Object { "  $_" })
    ""
    "Removed:"
    ($removed | Sort-Object | ForEach-Object { "  $_" })
) | Set-Content -LiteralPath $reportPath -Encoding UTF8

Write-Host "Kept $($kept.Count) extension directories."
Write-Host "Removed $($removed.Count) extension directories."
Write-Host "Report: $reportPath"
