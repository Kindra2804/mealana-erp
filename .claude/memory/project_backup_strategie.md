---
name: project-backup-strategie
description: "Backup-Plan für Live-Server (DB täglich, Bilder quartalsweise, Verschlüsselungs-Key getrennt) — geplant, noch nicht gebaut"
metadata: 
  node_type: memory
  type: project
  originSessionId: 11cd53c7-877f-4fd4-89a6-5f721bd74ce1
---

## Status: GEPLANT (Stand 2026-07-03), bis dahin nur manuelle Sicherungen

Besprochen direkt nach dem ersten Live-Server-Setup (siehe [[project_installationsanleitung]]). Jacky prüft erst seine Proxmox-Infrastruktur zuhause, bevor das sauber gebaut wird — bis dahin behilft er sich mit manuellen Sicherungen.

## Was gesichert werden muss, mit welcher Priorität

| Was | Frequenz | Warum |
|---|---|---|
| MariaDB-Dump (`mealana_erp`) | täglich | einzige Quelle der eigentlichen Geschäftsdaten |
| `erp\config\encryption.php` | bei Änderung, **getrennt** vom DB-Backup aufbewahren | ohne diesen Key sind alle AES-256-GCM-verschlüsselten Kundendaten für immer unlesbar — landet er zusammen mit einem DB-Backup an einem Ort, ist die Verschlüsselung witzlos |
| `erp\public\uploads\` + `erp\public\img\` (Artikelbilder, Logos) | quartalsweise reicht (Jackys bewusste Entscheidung) | echte Originaldaten, aber vertretbares Risiko für den Zeitraum |
| `erp\storage\` (generierte PDFs) | **keine eigene Sicherung nötig** | jederzeit aus DB-Daten + Twig-Templates neu erzeugbar (DokumentService) |

## Verschlüsselung des Backups selbst

Nicht zwingend zusätzlich nötig — die AES-256-GCM-verschlüsselten Kundenfelder sind im DB-Dump schon Chiffretext, ein gestohlener Dump allein bringt ohne den Key nichts. Zwei offene Punkte dazu:
- Nicht Feld-für-Feld verifiziert, ob wirklich *alles* Sensible (z.B. Lieferanten-IBAN, eigene EK-Preise/Handelsspannen — geschäftlich brisant, kein DSGVO-Thema) von der Verschlüsselung erfasst ist.
- Der Schutz gilt nur, solange `encryption.php` wirklich getrennt vom DB-Backup bleibt (siehe Tabelle oben).

## Speicherort — noch offen, Jacky evaluiert

- Hat einen Proxmox-Host zuhause im Büro — Idee: eigener Container/VM als lokales Backup-Ziel.
- Empfehlung meinerseits: **Proxmox Backup Server** (kostenloses Projekt derselben Firma) wäre naheliegend, macht dedupliziertes inkrementelles Backup, passt gut zum bestehenden Cronjob-Muster (Mahnwesen/BFR-Nachsignierung laufen schon als Windows Task Scheduler-Jobs, siehe [[project_installationsanleitung]] Schritt 12).
- 3-2-1-Regel im Hinterkopf behalten: mind. 2 Medien, 1 davon nicht am selben Standort (Cloud wie Backblaze B2 + rclone war die ursprüngliche Idee aus [[project_infrastruktur]], ~2€/Monat) — Proxmox-Backup allein deckt nur die lokale Kopie ab, nicht den Diebstahl-/Brand-Fall vor Ort.

**Why:** Erstes Live-System steht (siehe [[project_installationsanleitung]]), damit wird Backup vom theoretischen Punkt zur echten Notwendigkeit — aber Jacky wollte die Infrastruktur-Entscheidung (Proxmox) nicht überstürzen.
**How to apply:** Nicht von selbst umsetzen. Erst wieder aufgreifen, wenn Jacky von sich aus die Proxmox-Recherche abgeschlossen hat und "Backup jetzt bauen" sagt.

## Beinahe-Datenverlust 2026-07-06

XAMPP/MariaDB (10.4.32, lokale Dev-Maschine) startete nicht mehr: InnoDB-Redo-Log korrupt ("Missing MLOG_CHECKPOINT", Checkpoint-LSN lag hinter Log-Ende — mehr als ein normaler unsauberer Shutdown). Kein Backup vorhanden, da Strategie oben noch nicht gebaut ist. Rettung gelang über `innodb_force_recovery=6` (Redo-Log-Rollforward komplett übersprungen) → vollständiger `mysqldump` möglich → Datenverzeichnis frisch initialisiert → Dump zurückgespielt. Kein Datenverlust, aber knapp (nur weil die Tabellendateien selbst noch intakt waren).

**Why:** Zeigt, dass "manuelle Sicherung bei Bedarf" in der Praxis nicht passiert — es gab schlicht keine, als sie gebraucht wurde.
**How to apply:** Falls Jacky das Thema nochmal aufschiebt, diesen Vorfall als konkretes Beispiel nennen dürfen, warum wenigstens ein simples tägliches `mysqldump`-Script (auch ohne fertige Proxmox-Lösung) schon jetzt Sinn hätte — ersetzt nicht die spätere 3-2-1-Lösung, wäre aber besser als nichts.
