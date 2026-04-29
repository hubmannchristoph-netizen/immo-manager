# Sync getrackte Markdowns nach Obsidian-Vault.
# Wird als PostToolUse-Hook auf Write/Edit aufgerufen.
# Liest hook-event JSON von stdin, prueft tool_input.file_path gegen Sync-Mapping,
# kopiert Datei in den passenden Vault-Unterordner.
# Silent fail in jedem Fehlerfall, exit immer 0 (blockt den Tool-Call nie).

$ErrorActionPreference = "SilentlyContinue"

try {
    $vaultRoot = "C:\Users\ch\Meine Ablage\AI Knowledge (2nd Brain)\2nd Brain\Projekte\ImmoManager"

    if (-not (Test-Path -LiteralPath $vaultRoot)) { exit 0 }

    $stdinJson = [Console]::In.ReadToEnd()
    if ([string]::IsNullOrWhiteSpace($stdinJson)) { exit 0 }

    $eventObj = $stdinJson | ConvertFrom-Json
    $filePath = $eventObj.tool_input.file_path
    if ([string]::IsNullOrWhiteSpace($filePath)) { exit 0 }

    # Pfad-Normalisierung: Backslashes als Standard.
    $normalized = ($filePath -replace '/', '\')

    # Sicherheitsgate: nur Files aus dem immo-manager-Repo synchronisieren.
    if ($normalized -notmatch '\\immo-manager\\') { exit 0 }

    # Mapping: source-Pattern -> Vault-Subpath
    $dest = $null
    if     ($normalized -match '\\docs\\superpowers\\specs\\[^\\]+\.md$')    { $dest = Join-Path $vaultRoot 'specs' }
    elseif ($normalized -match '\\docs\\superpowers\\plans\\[^\\]+\.md$')    { $dest = Join-Path $vaultRoot 'plans' }
    elseif ($normalized -match '\\DOCUMENTATION\.md$')                       { $dest = Join-Path $vaultRoot 'docs' }
    elseif ($normalized -match '\\readme\.txt$')                             { $dest = Join-Path $vaultRoot 'docs' }
    elseif ($normalized -match '\\templates\\AI_API_GUIDE\.md$')             { $dest = Join-Path $vaultRoot 'docs\templates' }
    else { exit 0 }

    if (-not (Test-Path -LiteralPath $normalized)) { exit 0 }

    if (-not (Test-Path -LiteralPath $dest)) {
        New-Item -ItemType Directory -Path $dest -Force | Out-Null
    }

    Copy-Item -LiteralPath $normalized -Destination $dest -Force
} catch {
    # Silent fail.
}

exit 0
