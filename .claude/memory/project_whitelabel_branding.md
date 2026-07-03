---
name: project-whitelabel-branding
description: "Geplant: konfigurierbarer URL-Pfad statt fixem /mealana/ + Logo/Branding in Systemeinstellungen statt hart codiert"
metadata: 
  node_type: memory
  type: project
  originSessionId: 11cd53c7-877f-4fd4-89a6-5f721bd74ce1
---

## Status: GEPLANT, noch nicht gebaut (Stand 2026-07-03)

Zwei offene Punkte, die Jacky explizit für später vorgemerkt haben will, im Kontext der geplanten Weitergabe an Tester/andere Betriebe (siehe [[project_installationsanleitung]]):

1. **URL-Pfad `/mealana/` konfigurierbar machen.** Aktuell ist dieser Pfad überall im Code hart verdrahtet (Navigation, JS, TinyMCE, Bild-Links — siehe `docs/installation.md` Schritt 3). Für eine echte Weitergabe an andere Betriebe müsste das generalisiert werden, damit z.B. Firma XY die Software unter `/xy-erp/` betreiben kann.
2. **Logo in shell_top ist fix.** Aktuell fest verdrahtetes MeaLana-Logo im Shell-Header. Zwei Optionen zur Diskussion, Entscheidung bewusst vertagt:
   - Logo konfigurierbar in Systemeinstellungen (analog zum bereits vorhandenen Shop-Logo-Upload unter `system_einstellungen`/`shops.logo_pfad`)
   - ODER: gar kein pro-Installation-Logo, sondern ein fixes Software-Marken-Logo/Schriftzug, der die Software selbst repräsentiert (nicht die Kundenfirma)

**Why:** Beide Punkte wurden beim Schreiben der Installationsanleitung als Lücken für eine Weitergabe an Dritte identifiziert (siehe `docs/installation.md` Anhang B). Kein akuter Blocker für die eigene Live-Umgebung, aber notwendig bevor die Software wirklich an einen fremden Betrieb weitergegeben wird.
**How to apply:** Nicht von selbst angehen — erst wenn Jacky das aktiv anspricht bzw. wenn eine konkrete Weitergabe ansteht. Logo-Frage (Kunden-Branding vs. Software-Branding) ist noch offen und braucht erst eine Entscheidung von Jacky.
