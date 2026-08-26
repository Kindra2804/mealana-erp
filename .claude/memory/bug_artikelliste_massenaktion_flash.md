---
name: bug-artikelliste-massenaktion-flash
description: "BEHOBEN 2026-08-26: Erfolgsmeldung nach Massenaktionen in artikel/liste.php (aktivieren/deaktivieren/Auslauf/erneut synchronisieren) landete in Session, wurde aber von liste.php nie angezeigt -- tauchte irgendwann verzoegert auf einer ganz anderen Seite auf"
metadata:
  node_type: memory
  type: project
  originSessionId: 1fe37890-2d54-4c96-bcae-65d709049cbe
  modified: 2026-08-26T13:41:15.333Z
---

Jacky fiel beim Testen der neuen "Erneut synchronisieren (Shop)"-Massenaktion auf: die Erfolgsmeldung ("4 Artikel für erneuten Shop-Sync markiert...") erschien nicht sofort nach der Aktion, sondern erst irgendwann später, wenn er in ein anderes Tab/Modul wechselte.

**Root Cause:** `artikel/massenupdate.php` (AJAX-Endpunkt für alle 5 Massenaktionen: aktivieren, deaktivieren, Auslauf markieren/entfernen, erneut synchronisieren) schreibt die Erfolgsmeldung nach altem Muster in `$_SESSION['erfolg']`. `artikel/liste.php` selbst hat diese Session-Variable aber **nie gelesen/angezeigt** -- komplett vergessen beim Bau der Seite, kein Einzelfall der neuen Mindestabnahme-Aktion. Das Frontend-JS in `liste.php` macht nach erfolgreicher AJAX-Antwort nur `location.reload()`, ohne die Nachricht selbst anzuzeigen. Die Session-Variable blieb dadurch bis zum nächsten Seitenaufruf bestehen, der die (im ganzen ERP unkoordinierte, jede Seite liest/löscht selbst) `$_SESSION['erfolg']`-Konvention tatsächlich implementiert -- z.B. `artikel/detail.php` oder eine andere Seite, irgendwann später.

**Fix:** `artikel/liste.php` liest jetzt (nach dem State-Redirect-Block, damit die Nachricht einen Redirect unbeschadet übersteht) `$_SESSION['erfolg']`/`$_SESSION['fehler']`, zeigt sie in derselben `.success-banner`/`.error-banner`-Optik wie `detail.php` direkt nach `shell_top.php` an, plus 3s-Auto-Hide (gleiche Konvention wie überall sonst, siehe [[feedback_banner_autohide]]).

**Getestet:** `php -l`. Kein Browser-Test in dieser Session (kein Login-Zugriff) -- Jacky sollte einmal eine Massenaktion (z.B. Aktivieren/Deaktivieren) auf der Artikelliste ausprobieren und prüfen, dass die Meldung jetzt sofort nach dem Reload erscheint und nach ~3s verschwindet.

**How to apply:** Kein systematischer Mechanismus für Flash-Messages im ERP (jede Seite implementiert es einzeln) -- bei künftigen neuen Listen-/Massenaktion-Seiten daran denken, das nicht zu vergessen wie hier.
