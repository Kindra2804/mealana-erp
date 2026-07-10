<?php

/**
 * Zentrale Tabelle: welche Berechtigung braucht welche Seite.
 *
 * Statt in jeder der ~230 Seiten unter public/ einen eigenen Auth::kann()-Check
 * zu pflegen, steht die Zuordnung Verzeichnis+Dateiname → Berechtigung hier an
 * einer Stelle. auth_check.php schlägt beim Laden jeder Seite hier nach und
 * blockt, wenn die Berechtigung fehlt (siehe Auth::pruefeSeite()).
 *
 * Fehlt ein Eintrag für eine Datei, wird sie NICHT blockiert (Fallback: nur
 * Login-Pflicht wie bisher) — das verhindert, dass eine vergessene neue Seite
 * versehentlich alle aussperrt. Neue Seiten sollen hier bewusst ergänzt werden.
 */
final class Zugriffsregeln
{
    /** @var array<string, array<string, string>> */
    private static array $regeln = [

        // dashboard.php hat KEINEN Eintrag hier — bei fehlendem dashboard.zugriff
        // leitet dashboard.php selbst auf ein passendes Modul um statt die generische
        // Kein-Zugriff-Seite zu zeigen (siehe Auth::startseiteFuerBenutzer()).

        // === Artikel-Stammdaten ===
        'artikel' => [
            'liste.php'                     => 'artikel.anzeigen',
            'detail.php'                    => 'artikel.anzeigen',
            'kategorien_verwalten.php'      => 'artikel.anzeigen',
            'merkmale_verwalten.php'        => 'artikel.anzeigen',
            'bewegungslog_ajax.php'         => 'artikel.anzeigen',
            'bewegungslog_tabelle.php'      => 'artikel.anzeigen',
            'ean_check.php'                 => 'artikel.anzeigen',
            'artikel_vater_suche.php'       => 'artikel.anzeigen',
            'varkombi_generator.php'        => 'artikel.anzeigen',
            'kopieren.php'                  => 'artikel.anzeigen',

            'neu.php'                       => 'artikel.anlegen',
            'speichern.php'                 => 'artikel.anlegen',
            'kopieren_speichern.php'        => 'artikel.anlegen',
            'import.php'                    => 'artikel.anlegen',
            'kategorie_neu.php'             => 'artikel.anlegen',
            'kategorie_erstellen.php'       => 'artikel.anlegen',
            'variante_neu.php'              => 'artikel.anlegen',
            'variante_speichern.php'        => 'artikel.anlegen',
            'varkombi_erstellen.php'        => 'artikel.anlegen',

            'bearbeiten.php'                => 'artikel.bearbeiten',
            'aktualisieren.php'             => 'artikel.bearbeiten',
            'variante_bearbeiten.php'       => 'artikel.bearbeiten',
            'variante_aktualisieren.php'    => 'artikel.bearbeiten',
            'massenupdate.php'              => 'artikel.bearbeiten',
            'spalten_einstellung_speichern.php' => 'artikel.bearbeiten',
            'seo_speichern.php'             => 'artikel.bearbeiten',
            'merkmal_ajax.php'              => 'artikel.bearbeiten',
            'merkmale_speichern.php'        => 'artikel.bearbeiten',
            'bild_ajax.php'                 => 'artikel.bearbeiten',
            'bild_upload.php'               => 'artikel.bearbeiten',
            'kategorie_bearbeiten_ajax.php' => 'artikel.bearbeiten',
            'kategorie_sort_ajax.php'       => 'artikel.bearbeiten',
            'bulk_kategorie_speichern.php'  => 'artikel.bearbeiten',
            'artikel_lieferant_speichern.php' => 'artikel.bearbeiten',
            // Preise sind ein Tab im Artikel-Modul, keine eigene Berechtigung vorhanden
            'preis_speichern.php'           => 'artikel.bearbeiten',
            'uvp_speichern.php'             => 'artikel.bearbeiten',
            'staffelpreis_speichern.php'    => 'artikel.bearbeiten',
            'sale_override_speichern.php'   => 'artikel.bearbeiten',
            // Bestand/Lagerbewegung direkt am Artikel (Tab "Lager")
            'lager_schnell_we.php'          => 'bestand.bearbeiten',

            'delete.php'                    => 'artikel.loeschen',
            'bild_loeschen.php'             => 'artikel.loeschen',
            'kategorie_loeschen_ajax.php'   => 'artikel.loeschen',
            'preis_loeschen.php'            => 'artikel.loeschen',
            'staffelpreis_loeschen.php'     => 'artikel.loeschen',
            'sale_override_loeschen.php'    => 'artikel.loeschen',

            // Achsen-Zuweisung am Artikel gehört fachlich zu "Varianten"
            'achsen_zuweisen.php'           => 'varianten.anzeigen',
            'achsen_zuweisen_ajax.php'      => 'varianten.bearbeiten',
            'achsen_speichern.php'          => 'varianten.bearbeiten',
        ],

        // === Achsen (Variantenachsen) — fachlich Teil von "Varianten" ===
        'achsen' => [
            'liste.php'                    => 'varianten.anzeigen',
            'achse_aktualisieren_ajax.php'  => 'varianten.bearbeiten',
            'sort_ajax.php'                 => 'varianten.bearbeiten',
            'achse_sort_tree_ajax.php'      => 'varianten.bearbeiten',
            'achse_speichern_ajax.php'      => 'varianten.anlegen',
            'loeschen.php'                  => 'varianten.loeschen',
            'achse_loeschen_ajax.php'       => 'varianten.loeschen',
        ],

        // === Preis-Aktionen (Sale/Lieferanten-Aktionen) — Tab unter Artikel ===
        'aktionen' => [
            'index.php'                    => 'artikel.anzeigen',
            'liste.php'                    => 'artikel.anzeigen',
            'bearbeiten.php'                => 'artikel.bearbeiten',
            'aktion_speichern.php'          => 'artikel.bearbeiten',
            'aktion_preise_speichern.php'   => 'artikel.bearbeiten',
            'aktion_artikel_laden.php'      => 'artikel.anzeigen',
            'aktion_kategorie_ajax.php'     => 'artikel.anzeigen',
            'aktion_starten_ajax.php'       => 'artikel.bearbeiten',
            'aktion_loeschen.php'           => 'artikel.loeschen',
        ],

        // === Hersteller — Satellit von Artikel-Stammdaten ===
        'hersteller' => [
            'liste.php'          => 'artikel.anzeigen',
            'speichern.php'      => 'artikel.bearbeiten',
            'aktualisieren.php'  => 'artikel.bearbeiten',
            'schnell_speichern.php' => 'artikel.bearbeiten',
            'loeschen.php'       => 'artikel.loeschen',
        ],

        // === Lager (Standorte, Übersicht, Picklisten, Chargen-Nachtrag) ===
        'lager' => [
            'uebersicht.php'                 => 'bestand.anzeigen',
            'wareneingang.php'                => 'wareneingang.buchen',
            'wareneingang_speichern.php'       => 'wareneingang.buchen',
            'variante_suche.php'               => 'bestand.anzeigen',
            'nachtrag_liste.php'               => 'bestand.anzeigen',
            'nachtrag_speichern.php'           => 'bestand.korrigieren',
            'picklisten.php'                   => 'lager.anzeigen',
            'pickliste_erstellen.php'          => 'lager.bearbeiten',
            'pickliste_loeschen.php'           => 'lager.bearbeiten',
            'pickliste_pdf.php'                => 'lager.anzeigen',
            'verwaltung.php'                   => 'lager.anzeigen',
            'verwaltung_speichern.php'         => 'lager.anlegen',
            'verwaltung_aktualisieren.php'     => 'lager.bearbeiten',
            'verwaltung_status_setzen.php'     => 'lager.bearbeiten',
        ],

        // === Wareneingang (Einkauf → Lager) ===
        'wareneingang' => [
            'index.php'                        => 'wareneingang.buchen',
            'detail.php'                        => 'wareneingang.buchen',
            'artikel_suche.php'                 => 'wareneingang.buchen',
            'artikel_vorbereiten.php'           => 'wareneingang.buchen',
            'artikel_bearbeiten_vorbereiten.php' => 'wareneingang.buchen',
            'chargen_ajax.php'                  => 'wareneingang.buchen',
            'durchlauf_add.php'                 => 'wareneingang.buchen',
            'durchlauf_clear.php'               => 'wareneingang.buchen',
            'bestellung_aus_durchlauf.php'      => 'wareneingang.buchen',
            'speichern.php'                     => 'wareneingang.buchen',
            'abschliessen.php'                  => 'wareneingang.bearbeiten',
        ],

        // === Bestellungen (Lieferanten-Bestellwesen) ===
        'bestellungen' => [
            'liste.php'            => 'bestellwesen.anzeigen',
            'detail.php'            => 'bestellwesen.anzeigen',
            'artikel_ajax.php'      => 'bestellwesen.anzeigen',
            'reserviert_ajax.php'   => 'bestellwesen.anzeigen',
            'neu.php'               => 'bestellwesen.anlegen',
            'speichern.php'         => 'bestellwesen.anlegen',
            'bearbeiten.php'        => 'bestellwesen.bearbeiten',
            'aktualisieren.php'     => 'bestellwesen.bearbeiten',
            'rechnung_speichern.php' => 'bestellwesen.bearbeiten',
            'stornieren.php'        => 'bestellwesen.bearbeiten',
            'dokument_erstellen.php' => 'bestellwesen.bearbeiten',
            'mail_vorschau.php'     => 'bestellwesen.bearbeiten',
            'mail_senden.php'       => 'bestellwesen.bearbeiten',
            'dokument_download.php' => 'bestellwesen.anzeigen',
        ],

        // === Lieferanten-Stammdaten ===
        'lieferanten' => [
            'liste.php'                  => 'lieferanten.anzeigen',
            'detail.php'                  => 'lieferanten.anzeigen',
            'neu.php'                     => 'lieferanten.anlegen',
            'speichern.php'               => 'lieferanten.anlegen',
            'vertreter_neu.php'           => 'lieferanten.anlegen',
            'vertreter_speichern.php'     => 'lieferanten.anlegen',
            'zugang_neu.php'              => 'lieferanten.anlegen',
            'zugang_speichern.php'        => 'lieferanten.anlegen',
            'bearbeiten.php'              => 'lieferanten.bearbeiten',
            'aktualisieren.php'           => 'lieferanten.bearbeiten',
            'vertreter_bearbeiten.php'    => 'lieferanten.bearbeiten',
            'vertreter_aktualisieren.php' => 'lieferanten.bearbeiten',
            'zugang_bearbeiten.php'       => 'lieferanten.bearbeiten',
            'zugang_aktualisieren.php'    => 'lieferanten.bearbeiten',
            'delete.php'                  => 'lieferanten.loeschen',
            'vertreter_delete.php'        => 'lieferanten.loeschen',
            'zugang_delete.php'           => 'lieferanten.loeschen',
        ],

        // === Kunden ===
        'kunden' => [
            'liste.php'               => 'kunden.anzeigen',
            'detail.php'               => 'kunden.anzeigen',
            'neu.php'                  => 'kunden.anlegen',
            'speichern.php'            => 'kunden.anlegen',
            'adresse_speichern.php'    => 'kunden.anlegen',
            'bearbeiten.php'           => 'kunden.bearbeiten',
            'aktualisieren.php'        => 'kunden.bearbeiten',
            'adresse_aktualisieren.php' => 'kunden.bearbeiten',
            'consent_speichern.php'    => 'kunden.bearbeiten',
            'status_setzen.php'        => 'kunden.bearbeiten',
            'adresse_loeschen.php'     => 'kunden.loeschen',
        ],

        // === Partner (Mietfach/Kommission) ===
        'partner' => [
            'liste.php'             => 'partner.anzeigen',
            'mietfaecher.php'        => 'partner.anzeigen',
            'speichern.php'          => 'partner.anlegen',
            'fach_speichern.php'     => 'partner.anlegen',
            'vertrag_speichern.php'  => 'partner.anlegen',
            'aktualisieren.php'      => 'partner.bearbeiten',
            'fach_aktualisieren.php' => 'partner.bearbeiten',
            'status_setzen.php'      => 'partner.bearbeiten',
            'vertrag_beenden.php'    => 'partner.bearbeiten',
        ],

        // === Aufträge / Verkauf ===
        'auftraege' => [
            'liste.php'                => 'auftraege.anzeigen',
            'detail.php'                => 'auftraege.anzeigen',
            'artikel_ajax.php'          => 'auftraege.anzeigen',
            'kunden_ajax.php'           => 'auftraege.anzeigen',
            'status_ajax.php'           => 'auftraege.anzeigen',
            'dokument_download.php'     => 'auftraege.anzeigen',
            'neu.php'                   => 'auftraege.anlegen',
            'speichern.php'             => 'auftraege.anlegen',
            'bearbeiten.php'            => 'auftraege.bearbeiten',
            'aktualisieren.php'         => 'auftraege.bearbeiten',
            'zahlung_buchen.php'        => 'auftraege.bearbeiten',
            'zahlungsart_aendern.php'   => 'auftraege.bearbeiten',
            'dokument_erstellen.php'    => 'auftraege.bearbeiten',
            'gutschrift_erstellen.php'  => 'auftraege.bearbeiten',
            'gutschrift_speichern.php'  => 'auftraege.bearbeiten',
            'stornieren.php'            => 'auftraege.stornieren',
        ],

        // === Dokumentenarchiv (nur Lesen, reine Übersicht) ===
        'dokumente' => [
            'index.php' => 'auftraege.anzeigen',
        ],

        // === Versand(-klassen) ===
        'versand' => [
            'index.php'                    => 'versand.anzeigen',
            'speichern.php'                 => 'versand.bearbeiten',
            'versandklasse_speichern.php'   => 'versand.bearbeiten',
            'versandklasse_loeschen.php'    => 'versand.bearbeiten',
        ],

        // === Kasse/POS ===
        // Absichtlich zurückhaltend: Tages-/Abschluss-Funktionen brauchen kasse.stoppen,
        // Verwaltung/Konfiguration kasse.verwaltung, der Rest (Verkaufsbetrieb) kasse.starten.
        'kasse' => [
            'index.php'                  => 'kasse.starten',
            'bon.php'                    => 'kasse.starten',
            'bon_offline.php'            => 'kasse.starten',
            'bon_speichern.php'          => 'kasse.starten',
            'bon_druck.php'              => 'kasse.starten',
            'bon_a4.php'                 => 'kasse.starten',
            'ajax_bon_stornieren.php'    => 'kasse.starten',
            'bon_journal.php'            => 'kasse.starten',
            'ajax_kundenanzeige_sync.php' => 'kasse.starten',
            'ajax_artikel.php'           => 'kasse.starten',
            'ajax_auftrag_laden.php'     => 'kasse.starten',
            'ajax_kunden_suche.php'      => 'kasse.starten',
            'ajax_kassenlade.php'        => 'kasse.starten',
            'ajax_schnellwahl.php'       => 'kasse.starten',
            'ajax_parken.php'            => 'kasse.starten',
            'ajax_messe.php'             => 'kasse.starten',
            'offene_auswahl.php'         => 'kasse.starten',
            'offene_auswahl_speichern.php' => 'kasse.starten',
            'offene_auswahl_verarbeiten.php' => 'kasse.starten',
            'messe_vorbereiten.php'      => 'kasse.starten',
            'messe_rueckkehr.php'       => 'kasse.starten',
            'nacherfassung.php'          => 'kasse.starten',
            'nacherfassung_retry.php'    => 'kasse.starten',
            'ajax_nullbon.php'           => 'kasse.stoppen',
            'kassenbuch.php'             => 'kasse.stoppen',
            'kassenbuch_speichern.php'   => 'kasse.stoppen',
            'kassensturz.php'            => 'kasse.stoppen',
            'kassensturz_speichern.php'  => 'kasse.stoppen',
            'abschluss_liste.php'        => 'kasse.stoppen',
            'abschluss_druck.php'        => 'kasse.stoppen',
            'abschluss_periode.php'      => 'kasse.stoppen',
            'abschluss_mail.php'         => 'kasse.stoppen',
            'abschluss_periode_mail.php' => 'kasse.stoppen',
            'kassen_einstellungen.php'   => 'kasse.verwaltung',
        ],

        // === Packplatz (Kommissionierung/Versand/Retoure) ===
        'packplatz' => [
            'index.php' => 'versand.anzeigen',
        ],
        'packplatz/warenausgang' => [
            'index.php'            => 'versand.anzeigen',
            'scan.php'              => 'versand.bearbeiten',
            'abschliessen.php'      => 'versand.bearbeiten',
            'ajax_status_setzen.php' => 'versand.bearbeiten',
            'chargen_ajax.php'      => 'versand.bearbeiten',
            'tracking_eintragen.php' => 'versand.bearbeiten',
        ],
        'packplatz/wareneingang' => [
            'index.php'          => 'wareneingang.buchen',
            'detail.php'          => 'wareneingang.buchen',
            'speichern.php'       => 'wareneingang.buchen',
            'frei.php'            => 'wareneingang.buchen',
            'frei_speichern.php'  => 'wareneingang.buchen',
            'ean_nachtragen.php'  => 'wareneingang.buchen',
            'abschliessen.php'    => 'wareneingang.bearbeiten',
        ],
        'packplatz/intern' => [
            'index.php'                    => 'lager.anzeigen',
            'artikel_ajax.php'              => 'lager.anzeigen',
            'umbuchen.php'                   => 'lager.bearbeiten',
            'zustand_aendern.php'            => 'lager.bearbeiten',
            'zustand_umbuchen.php'           => 'lager.bearbeiten',
            'zustand_anlegen_umbuchen.php'   => 'lager.bearbeiten',
        ],
        'packplatz/retoure' => [
            'index.php'      => 'packplatz.retoure',
            'detail.php'      => 'packplatz.retoure',
            'speichern.php'   => 'packplatz.retoure',
        ],

        // === Benutzerverwaltung ===
        'benutzer' => [
            'liste.php'              => 'benutzer.anzeigen',
            'profil.php'              => null, // jeder eingeloggte Benutzer darf sein eigenes Profil sehen
            'speichern.php'           => 'benutzer.anlegen',
            'aktualisieren.php'       => 'benutzer.bearbeiten',
            'status_setzen.php'       => 'benutzer.bearbeiten',
            'link_erneut_senden.php'  => 'benutzer.bearbeiten',
        ],

        // === Rollen & Rechte-Matrix ===
        'rollen' => [
            'matrix.php'               => 'benutzer.anzeigen',
            'berechtigung_setzen.php'  => 'benutzer.bearbeiten',
        ],

        // === Einstellungen ===
        'einstellungen' => [
            'index.php'                       => 'einstellungen.anzeigen',
            'speichern.php'                    => 'einstellungen.bearbeiten',
            'test_mail.php'                    => 'einstellungen.bearbeiten',
            'kasse_edit.php'                    => 'kasse.verwaltung',
            'kasse_speichern.php'               => 'kasse.verwaltung',
            'kasse_registrierung.php'           => 'kasse.verwaltung',
            'kasse_registrierung_speichern.php' => 'kasse.verwaltung',
        ],

        // === Buchhaltung ===
        'buchhaltung' => [
            'artikel_gruppen.php'          => 'buchhaltung.anzeigen',
            'artikel_gruppen_speichern.php' => 'buchhaltung.anzeigen',
            'artikel_gruppen_loeschen.php'  => 'buchhaltung.anzeigen',
        ],
    ];

    /**
     * @return string|null Berechtigungsname, `null` = kein Rechtecheck nötig
     *                      (nur Login-Pflicht), `false`-artiger Sonderfall
     *                      "kein Eintrag" liefert ebenfalls null (siehe Klassenkopf).
     */
    public static function benoetigteBerechtigung(string $verzeichnis, string $datei): ?string
    {
        $verzeichnis = str_replace('\\', '/', $verzeichnis);
        return self::$regeln[$verzeichnis][$datei] ?? null;
    }

    /**
     * Dateien, die bei fehlender Berechtigung eine JSON-Fehlerantwort statt einer
     * Weiterleitung erwarten (per grep nach "Content-Type: application/json"
     * bzw. echo json_encode(...) ermittelt, Stand 2026-07-05). Antwortform folgt
     * der Projekt-Konvention {erfolg:false, fehler:"..."} (siehe js/artikel*.js).
     *
     * @var array<string, list<string>>
     */
    private static array $jsonEndpunkte = [
        'rollen'      => ['berechtigung_setzen.php'],
        'benutzer'    => ['link_erneut_senden.php', 'status_setzen.php', 'aktualisieren.php', 'speichern.php'],
        'lager'       => ['verwaltung_status_setzen.php', 'verwaltung_aktualisieren.php', 'verwaltung_speichern.php', 'variante_suche.php'],
        'kasse'       => [
            'ajax_messe.php', 'bon_speichern.php', 'ajax_parken.php', 'ajax_nullbon.php',
            'abschluss_periode_mail.php', 'abschluss_mail.php', 'ajax_auftrag_laden.php',
            'ajax_kunden_suche.php', 'ajax_kassenlade.php', 'ajax_schnellwahl.php',
            'ajax_artikel.php', 'offene_auswahl_speichern.php',
            'ajax_bon_stornieren.php', 'ajax_kundenanzeige_sync.php',
        ],
        'artikel'     => [
            'bild_upload.php', 'bulk_kategorie_speichern.php', 'bild_ajax.php', 'bild_loeschen.php',
            'sale_override_loeschen.php', 'sale_override_speichern.php', 'kategorie_erstellen.php',
            'kategorie_bearbeiten_ajax.php', 'artikel_lieferant_speichern.php', 'merkmale_speichern.php',
            'merkmal_ajax.php', 'achsen_zuweisen_ajax.php', 'kategorie_sort_ajax.php', 'ean_check.php',
            'kategorie_neu.php', 'kategorie_loeschen_ajax.php', 'artikel_vater_suche.php',
            'preis_loeschen.php', 'staffelpreis_loeschen.php', 'staffelpreis_speichern.php',
            'uvp_speichern.php', 'preis_speichern.php',
        ],
        'aktionen'    => [
            'aktion_speichern.php', 'aktion_preise_speichern.php', 'aktion_artikel_laden.php',
            'aktion_loeschen.php', 'aktion_starten_ajax.php', 'aktion_kategorie_ajax.php',
        ],
        'achsen'      => ['achse_sort_tree_ajax.php', 'achse_loeschen_ajax.php', 'achse_aktualisieren_ajax.php', 'achse_speichern_ajax.php', 'sort_ajax.php'],
        'hersteller'  => ['schnell_speichern.php', 'aktualisieren.php', 'speichern.php'],
        'partner'     => ['vertrag_beenden.php', 'vertrag_speichern.php', 'fach_aktualisieren.php', 'fach_speichern.php', 'status_setzen.php', 'aktualisieren.php', 'speichern.php'],
        'auftraege'   => ['zahlung_buchen.php', 'status_ajax.php', 'kunden_ajax.php', 'artikel_ajax.php'],
        'einstellungen' => ['test_mail.php'],
        'bestellungen'  => ['artikel_ajax.php', 'reserviert_ajax.php'],
        'wareneingang'  => ['durchlauf_clear.php', 'durchlauf_add.php', 'chargen_ajax.php', 'artikel_suche.php'],
        'packplatz/intern'      => ['zustand_anlegen_umbuchen.php', 'umbuchen.php', 'artikel_ajax.php', 'zustand_aendern.php', 'zustand_umbuchen.php'],
        'packplatz/warenausgang' => ['chargen_ajax.php', 'ajax_status_setzen.php'],
        'packplatz/wareneingang' => ['ean_nachtragen.php'],
    ];

    public static function istJsonEndpunkt(string $verzeichnis, string $datei): bool
    {
        $verzeichnis = str_replace('\\', '/', $verzeichnis);
        return in_array($datei, self::$jsonEndpunkte[$verzeichnis] ?? [], true);
    }
}
