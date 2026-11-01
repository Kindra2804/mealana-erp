# Lizenzen ausstellen (nur für NAHTLOS / Jacky)

Gehört **nicht** zum Kunden-Paket: `tools/` und `erp/src/modules/lizenzgenerator/` beim Kunden weglassen.
Ohne diesen Ordner ist der Generator im ERP unsichtbar; ohne den privaten Schlüssel ist der Code wertlos.

## Schlüssel
- Privat: `D:\ERP\lizenz_schluessel\nahtlos_lizenz_privat.pem` — **sichern** (USB-Stick + Passwort-Safe).
  Verlust = keine neuen Lizenzen mehr; ein neuer Schlüssel macht alle ausgestellten Lizenzen ungültig.
- Öffentlich: `erp/config/lizenz_public.pem` (geht mit jedem ERP mit).
- Ausgestellte Dateien zusätzlich: `D:\ERP\lizenz_schluessel\ausgestellt\`.

## Generator im eigenen ERP einschalten (einmalig)
    php tools/lizenz/generator_aktivieren.php            (Standardpfad des privaten Schlüssels)
    php tools/lizenz/generator_aktivieren.php --aus      (wieder ausschalten)
Danach: ··· → 🔑 Lizenzverwaltung → **Lizenzkunden / Lizenzen ausstellen**.

## Ablauf Lizenzverkauf
1. Auftrag an den Kunden mit Lizenz-Artikeln (Kategorie *Lizenzen*: LZ-GRUND, LZ-KASSE, LZ-KASSE-PLUS,
   LZ-SHOP, LZ-SHOP-PLUS, Module, LZ-WARTUNG je Jahr; BFR-Artikel nur zum Weiterverrechnen)
2. Im Auftrag **🔑 Lizenzdatei erstellen** → Module/Mengen/Wartung sind vorbelegt
   (neue Lizenz: 1. Jahr Wartung inklusive + gekaufte Jahre; bestehende: Restlaufzeit + gekaufte Jahre)
3. Firmenname + UID genau so, wie der Kunde sie in seinen Einstellungen → Firma hat
4. Ausstellen → `.lic` herunterladen → an den Kunden → dort ··· → Lizenzverwaltung → einspielen
5. Umfirmierung / Korrektur ohne Kauf: Lizenzkunden → **Neue Ausgabe**

## Lizenznummer `L-PPNNNN-CC`
PP = Produkt (10 = NAHTLOS ERP), NNNN = fortlaufend, CC = Prüfziffer mod 97 (wie IBAN).
Bleibt je Kunde gleich; jede neue Datei = nächste **Ausgabe**. L-100001-19 = MEALANA KG (Hauslizenz).

## Superadmin-Übergabe
Nur nach **schriftlicher** Anfrage von Inhaber/Geschäftsführung:

    php tools/lizenz/uebergabe_ausstellen.php --nr=U-2026-0001 --firma="Firma GmbH" --uid=ATU12345678

Gilt 7 Tage, einmal, nur für diese Firma. Kunde: Login-Seite → *Superadmin-Übergabe*.

## Notfall ohne ERP (Kommandozeile)
    php tools/lizenz/ausstellen.php --nr=L-100003-13 --firma="…" --uid=… --module=kasse:2,shop:1 --wartung=2027-11-01
