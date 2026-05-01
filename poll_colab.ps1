$lastHash = ""
while ($true) {
    $content = Get-Content -Path ".agents\workflows\colab.md" -Raw -ErrorAction SilentlyContinue
    if ($content) {
        $sha = [System.Security.Cryptography.SHA256]::Create()
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($content)
        $hashBytes = $sha.ComputeHash($bytes)
        $hash = ($hashBytes | ForEach-Object { $_.ToString("x2") }) -join ""
        $ts = Get-Date -Format "HH:mm:ss"
        if ($hash -ne $lastHash) {
            Write-Output "[$ts] CHANGE DETECTED in colab.md! Hash: $($hash.Substring(0,16))"
            $lastHash = $hash
        } else {
            Write-Output "[$ts] No change (hash: $($hash.Substring(0,16)))"
        }
    } else {
        $ts = Get-Date -Format "HH:mm:ss"
        Write-Output "[$ts] ERROR reading colab.md"
    }
    Start-Sleep -Seconds 15
}
