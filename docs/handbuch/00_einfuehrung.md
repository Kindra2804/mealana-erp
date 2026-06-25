# 00 — Einführung & Navigation

## Was ist das MeaLana ERP?

Das ERP ist das zentrale System für Artikel, Lager, Aufträge und Versand. Alles läuft hier zusammen — WooCommerce-Shop, Kasse, Packplatz und die Buchhaltungsexporte.

---

## Login

Adresse im Browser: `http://localhost/mealana/` (am Server) oder IP-Adresse über VPN.

1. Benutzernamen und Passwort eingeben
2. Auf **Anmelden** klicken
3. Bei falschem Passwort: Fehlermeldung erscheint — nochmal versuchen

> **Tipp:** Der Browser kann das Passwort speichern — bei einem geteilten PC besser nicht.

---

## Aufbau der Oberfläche

```
┌──────────────────────────────────────────────────────────┐
│  MEALANA ERP   Artikel  Lager  Aufträge  …  ⚙ Einstellungen │  ← Hauptnavigation (oben)
├────────────┬─────────────────────────────────────────────┤
│            │                                             │
│  Sidebar   │   Hauptbereich                              │
│  (links)   │                                             │
│            │                                             │
│  Unter-    │                                             │
│  punkte    │                                             │
│  des       │                                             │
│  Moduls    │                                             │
│            │                                             │
└────────────┴─────────────────────────────────────────────┘
```

- **Hauptnavigation oben:** Wechselt zwischen den Modulen (Artikel, Lager, Aufträge …)
- **Sidebar links:** Zeigt Unterpunkte des aktuellen Moduls
- **Hauptbereich:** Listen, Formulare, Detailseiten

---

## Rückmeldungen des Systems

Das System meldet nach jeder Aktion kurz ob es geklappt hat:

| Anzeige | Bedeutung |
|---------|-----------|
| Grüner Balken oben | Aktion erfolgreich (verschwindet nach ~3 Sekunden) |
| Roter Balken oben | Fehler — bitte lesen was drinsteht |
| Gelbes Ausrufezeichen (!) | Hinweis / Warnung — Aktion wurde trotzdem gespeichert |

---

## Navigation mit der Tastatur

| Taste | Funktion |
|-------|----------|
| `Tab` | Zum nächsten Feld springen |
| `Shift+Tab` | Zum vorherigen Feld |
| `Enter` | Formular absenden (wenn Cursor in einem Feld) |
| `Escape` | Modalfenster schließen |

---

## Packplatz (eigene Oberfläche)

Der Packplatz hat eine **eigene dunkle Oberfläche** — extra für Touchscreen und Barcode-Scanner optimiert.

Zugang: `http://localhost/mealana/packplatz/` oder über den Link im Auftragsmodul.

Details: siehe [05 Packplatz](05_packplatz.md).

---

## Abmelden

Oben rechts: Benutzer-Icon → **Abmelden**. Wichtig bei geteilten Computern!
