---
name: project-rksv-exporte-fremdumsatz
description: "Auf die Liste (Jacky 2026-11-14): RKSV-Exporte §131 + §7 (DEP über BFR) und Badge „Fremdumsatz“ für Partner-Aufträge/-Bons in der Auftragsliste"
metadata:
  type: project
---

**1. Prüfer-Exporte Kasse (Jacky 2026-11-14 nachgefragt):**
- **Nullbeleg:** gibt es schon. Manuell (Kasse → RKSV-Symbol → `ajax_nullbon.php`) und automatisch als Monats-Nullbeleg (`BfrService::sicherstelleMonatsNullbeleg`). Offen bleibt zu prüfen, ob der **Jahresbeleg** (Dezember) extra gedruckt bzw. mit der BMF-App geprüft werden muss.
- **§7-Export (DEP, Datenerfassungsprotokoll):** noch nicht gebaut. Der BFR liefert ihn direkt über `http://[EFR-IP]:8787/control/export` als DEP.json, immer für den kompletten Zeitraum. Eingrenzen geht nur direkt im BFR. Idee: Knopf in Kasse → RKSV, der das DEP.json holt und zum Download anbietet. Die Abfrage muss aus dem Netz der Kasse kommen, wie bei der bfr_url.
- **§131-Export (BAO, Datenexport für die Betriebsprüfung):** noch nicht gebaut. Gemeint ist der Export der Kassendaten (Bons/Positionen/Zahlungen) in lesbarer Form. Inhalt und Format vor dem Bau klären.

**2. Badge „Fremdumsatz“ in der Auftragsliste (Jacky 2026-11-14):**
- Kassen-Bons nur mit Partnerware (Fremdrechnung/Spende) bekommen heute bewusst keinen Spiegel-Auftrag, weil das nie KG-Umsatz ist (`KassenService`, Kommentar „Fremdrechnung/Spende ebenfalls nicht“). Deshalb fehlen sie in der Auftragsliste; das Kassen-Journal ist vollständig.
- Wunsch: Diese Bons (Auftrag mit Kanal Kasse + partner_id) und die A-IND-Aufträge in der Liste mit Badge **„Fremd“** unter der Nummer zeigen, wie „Widerrufen“. Kommen beide zusammen, nebeneinander und kurz („Fremd“ · „Widerr.“), damit die Zeile nicht zu hoch wird. Nicht in KG-Umsatz/Statistik zählen.

**How to apply:** Gehört nach der Reihung in [[project-roadmap-reihenfolge]] auf die Liste. Siehe auch [[project-rksv-bfr]] und [[project-partner-modul]].
