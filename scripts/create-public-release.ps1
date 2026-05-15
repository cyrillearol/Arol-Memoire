param(
    [string] $Target = "",
    [switch] $InitGit
)

$ErrorActionPreference = "Stop"

$source = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$parent = Split-Path $source -Parent

if ([string]::IsNullOrWhiteSpace($Target)) {
    $Target = Join-Path $parent "ELLIRYC_PLATEFORME_PUBLIC"
}

if (Test-Path $Target) {
    throw "Le dossier cible existe déjà: $Target. Supprimez-le manuellement ou choisissez un autre dossier avec -Target."
}

New-Item -ItemType Directory -Force -Path $Target | Out-Null

Push-Location $source
try {
    $files = git ls-files --cached --others --exclude-standard
} finally {
    Pop-Location
}

foreach ($file in $files) {
    $sourceFile = Join-Path $source $file

    if (-not (Test-Path -LiteralPath $sourceFile -PathType Leaf)) {
        continue
    }

    $targetFile = Join-Path $Target $file
    $targetDirectory = Split-Path $targetFile -Parent
    New-Item -ItemType Directory -Force -Path $targetDirectory | Out-Null
    Copy-Item -LiteralPath $sourceFile -Destination $targetFile -Force
}

$placeholders = @(
    "storage\app\public",
    "storage\logs",
    "storage\framework\views",
    "storage\framework\cache\data",
    "storage\framework\sessions",
    "storage\framework\testing"
)

foreach ($relativePath in $placeholders) {
    $directory = Join-Path $Target $relativePath
    New-Item -ItemType Directory -Force -Path $directory | Out-Null
    [System.IO.File]::WriteAllText((Join-Path $directory ".gitignore"), "*`n!.gitignore`n", [System.Text.UTF8Encoding]::new($false))
}

if ($InitGit) {
    Push-Location $Target
    try {
        git init | Out-Null
        git add .
        git commit -m "Initial public release" | Out-Null
        git branch -M main | Out-Null
    } finally {
        Pop-Location
    }
}

Write-Host "Export public prêt: $Target"
Write-Host "Ajoutez ensuite votre remote GitHub et poussez depuis ce dossier."