---
name: bug-schilder-partnerware-liste
description: "✅ behoben 2026-11-14 (12d8371): Fertige Schilder (ins Lager von Babsi/Indra übertragen) zählen in Kategoriebaum + Bestand, erscheinen aber nicht in der Artikelliste"
metadata:
  type: project
---

**Gemeldet von Jacky 2026-11-14:** Nachdem die Schilder ins Lager von Babsi (Indra-EU, Partnerware) übertragen wurden, zeigt der Kategoriebaum bei IndraDesign → Schilder → „Fertige Schilder“ 49 Artikel. Die Artikelliste zeigt bei dieser Kategorie aber „0 Hauptartikel“, obwohl der Haken „inkl. Partnerware“ gesetzt ist. Bestand und Lager zählen richtig.

**Why:** Die Kategoriezählung und der Listenfilter verwenden offenbar unterschiedliche Bedingungen. Verdacht: Konfigurator-/Schild-Artikeltyp oder der Partner-/Eigentümer-Filter in der Liste. Nicht geprüft.

**How to apply:** Erst angehen, wenn die Massenaktion „Preise ändern“ fertig ist (siehe [[project-idee-massenaktion-preis]]). Dann den WHERE-Teil der Liste mit der Kategoriezählung vergleichen. Siehe auch [[project-konfigurator-modul]] und [[project-partner-modul]].

**✅ Ursache + Fix 2026-11-14 (12d8371):** Es war kein Lagerfilter (Jackys Verdacht). `ArtikelRepository::findAll/countAll` klappt fertige Schilder immer unter ihr Layout (Regel vom 2026-11-13). Der Kategoriefilter prüft aber nur das Layout, und das liegt unter „Schilder“ → bei „Fertige Schilder“ kam 0 heraus. Jetzt gilt: nur einklappen, wenn das Layout auch in der gefilterten Kategorie steht (Bedingung `$conditions['schild']`). In einer Transaktion auf Dev nachgestellt und zurückgerollt. Auf Live kommt der Fix mit dem nächsten Paket.
