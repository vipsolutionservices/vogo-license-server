$root = Split-Path -Parent $PSScriptRoot
$pluginFolder = Join-Path $root "vogo-plugin"
$buildFolder = Join-Path $root "build"
$zipFile = Join-Path $buildFolder "vogo-plugin.zip"

Write-Host "Building plugin..."

if (Test-Path $zipFile) {
    Remove-Item $zipFile
}

Add-Type -AssemblyName System.IO.Compression.FileSystem

[System.IO.Compression.ZipFile]::CreateFromDirectory(
    $pluginFolder,
    $zipFile
)

Write-Host "ZIP created:"
Write-Host $zipFile