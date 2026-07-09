# BFR-Lokal-Proxy fuer die Offline-Kasse (Messe-Betrieb)
#
# Browser -> BFR direkt (fetch('http://127.0.0.1:8787/...')) wird IMMER von CORS blockiert,
# weil BFR keine Access-Control-Allow-Origin-Header schickt (bestaetigt 2026-07-09, siehe
# docs/offline_kasse_anleitung.md). Dieser Proxy laeuft auf demselben Geraet wie BFR und der
# Browser, macht den eigentlichen BFR-Call serverseitig (PowerShell unterliegt keinem CORS)
# und schickt die Antwort MIT korrekten CORS-Headern zurueck -- der Browser redet nur noch
# mit diesem Proxy, nie direkt mit BFR.
#
# Vor jedem Messe-Einsatz einmal per Doppelklick starten (muss waehrend der ganzen Messe
# im Hintergrund laufen bleiben, wie BFR selbst auch). Mit Strg+C beenden.
#
# Die Offline-Kasse (kasse_bon_offline.js) erwartet den Proxy fest auf Port 8788.

$proxyPort = 8788

function CorsHeader($resp) {
    $resp.Headers.Add("Access-Control-Allow-Origin", "*")
    $resp.Headers.Add("Access-Control-Allow-Methods", "GET, POST, OPTIONS")
    $resp.Headers.Add("Access-Control-Allow-Headers", "Content-Type")
}

function ProxyAufruf {
    param([string]$Method, [string]$Url, [string]$Body)
    try {
        $req = [System.Net.HttpWebRequest]::Create($Url)
        $req.Method = $Method
        $req.Timeout = 6000
        $req.ReadWriteTimeout = 6000
        if ($Body) {
            $req.ContentType = "text/xml"
            $bytes = [System.Text.Encoding]::UTF8.GetBytes($Body)
            $req.ContentLength = $bytes.Length
            $stream = $req.GetRequestStream()
            $stream.Write($bytes, 0, $bytes.Length)
            $stream.Close()
        }
        $resp = $req.GetResponse()
        $reader = New-Object System.IO.StreamReader($resp.GetResponseStream())
        $text = $reader.ReadToEnd()
        $reader.Close(); $resp.Close()
        return @{ ok = $true; body = $text }
    } catch {
        return @{ ok = $false; fehler = $_.Exception.Message }
    }
}

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://127.0.0.1:$proxyPort/")
try {
    $listener.Start()
} catch {
    Write-Host "FEHLER: Port $proxyPort konnte nicht geoeffnet werden (laeuft der Proxy evtl. schon?)" -ForegroundColor Red
    Write-Host $_.Exception.Message -ForegroundColor Red
    Read-Host "Enter zum Beenden"
    exit 1
}

Write-Host "BFR-Lokal-Proxy laeuft auf Port $proxyPort — waehrend der ganzen Messe offen lassen." -ForegroundColor Green
Write-Host "Zum Beenden: Strg+C" -ForegroundColor Yellow

try {
    while ($listener.IsListening) {
        $context = $listener.GetContext()
        $req  = $context.Request
        $resp = $context.Response
        CorsHeader $resp

        if ($req.HttpMethod -eq "OPTIONS") {
            # CORS-Preflight (Browser fragt vorab nach, weil Content-Type: text/xml
            # kein "simple request" ist) -- einfach mit 204 bestaetigen.
            $resp.StatusCode = 204
            $resp.OutputStream.Close()
            continue
        }

        if ($req.Url.LocalPath -ne "/proxy") {
            $resp.StatusCode = 404
            $resp.OutputStream.Close()
            continue
        }

        $zielUrl = $req.QueryString["url"]
        $body = $null
        if ($req.HttpMethod -eq "POST") {
            $reader = New-Object System.IO.StreamReader($req.InputStream)
            $body = $reader.ReadToEnd()
            $reader.Close()
        }

        $ergebnis = ProxyAufruf -Method $req.HttpMethod -Url $zielUrl -Body $body
        $json = $ergebnis | ConvertTo-Json -Compress
        $bytes = [System.Text.Encoding]::UTF8.GetBytes($json)
        $resp.ContentType = "application/json; charset=utf-8"
        $resp.Headers.Add("Cache-Control", "no-store")
        $resp.OutputStream.Write($bytes, 0, $bytes.Length)
        $resp.OutputStream.Close()
    }
} finally {
    $listener.Stop()
}
