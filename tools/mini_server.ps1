# Mini-Webserver + Proxy — dient dazu, bfr_lokaler_test.html ueber http:// bereitzustellen
# UND die eigentlichen BFR-Aufrufe serverseitig (PowerShell) statt aus dem Browser zu machen.
# Grund: BFR schickt grundsaetzlich keine Access-Control-Allow-Origin-Header, ein direkter
# fetch() aus dem Browser wird deshalb IMMER von CORS blockiert, egal von welchem Origin aus.
# PowerShell->BFR ist kein Browser-Request und unterliegt keiner CORS-Pruefung.
#
# Start: Rechtsklick -> "Mit PowerShell ausfuehren", dann im Browser http://127.0.0.1:8000/
# Beenden: Strg+C

$htmlPfad = Join-Path $PSScriptRoot "bfr_lokaler_test.html"
if (-not (Test-Path $htmlPfad)) {
    Write-Host "FEHLER: bfr_lokaler_test.html nicht im selben Ordner gefunden ($PSScriptRoot)" -ForegroundColor Red
    Read-Host "Enter zum Beenden"
    exit 1
}

function ProxyAufruf {
    param([string]$Method, [string]$Url, [string]$Body)
    $start = Get-Date
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
        $dauer = [int]((Get-Date) - $start).TotalMilliseconds
        return @{ ok = $true; body = $text; dauer_ms = $dauer }
    } catch {
        $dauer = [int]((Get-Date) - $start).TotalMilliseconds
        return @{ ok = $false; fehler = $_.Exception.Message; dauer_ms = $dauer }
    }
}

$listener = New-Object System.Net.HttpListener
$listener.Prefixes.Add("http://127.0.0.1:8000/")
$listener.Start()

Write-Host "Server laeuft: http://127.0.0.1:8000/  (im Browser oeffnen)" -ForegroundColor Green
Write-Host "Zum Beenden: Strg+C" -ForegroundColor Yellow

try {
    while ($listener.IsListening) {
        $context = $listener.GetContext()
        $req  = $context.Request
        $resp = $context.Response

        if ($req.Url.LocalPath -eq "/proxy") {
            $bfrUrl = $req.QueryString["url"]
            $body = $null
            if ($req.HttpMethod -eq "POST") {
                $reader = New-Object System.IO.StreamReader($req.InputStream)
                $body = $reader.ReadToEnd()
                $reader.Close()
            }
            $ergebnis = ProxyAufruf -Method $req.HttpMethod -Url $bfrUrl -Body $body
            $json = $ergebnis | ConvertTo-Json -Compress
            $bytes = [System.Text.Encoding]::UTF8.GetBytes($json)
            $resp.ContentType = "application/json; charset=utf-8"
            $resp.Headers.Add("Cache-Control", "no-store")
            $resp.OutputStream.Write($bytes, 0, $bytes.Length)
        } else {
            $html = Get-Content $htmlPfad -Raw -Encoding UTF8
            $bytes = [System.Text.Encoding]::UTF8.GetBytes($html)
            $resp.ContentType = "text/html; charset=utf-8"
            $resp.Headers.Add("Cache-Control", "no-store")
            $resp.OutputStream.Write($bytes, 0, $bytes.Length)
        }
        $resp.OutputStream.Close()
    }
} finally {
    $listener.Stop()
}
