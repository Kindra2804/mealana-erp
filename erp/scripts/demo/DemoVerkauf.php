<?php

require_once __DIR__ . '/../../src/modules/dokumente/DokumentRepository.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';

/**
 * Demo-Teil 3: 12 Monate Geschäftsverlauf, Tag für Tag bis heute.
 *
 * - Online-Bestellungen der 3 Shops (PayPal/Vorkasse/Nachnahme): Zahlung, Versand, Rechnung,
 *   Lagerabgang; junge Bestellungen in allen Zwischenständen, unbezahlte Vorkasse mit
 *   Erinnerung bzw. Auto-Storno, einzelne Widerrufe (Rechnungskorrektur + Rückerstattung)
 * - Telefon-/Händleraufträge auf Rechnung (offene + überfällige Posten, Mahnvorschläge)
 * - Kassenbons im Laden (Mo–Sa) mit Spiegel-Auftrag wie KassenService, danach Z-Bons
 *   über den echten KassenService (wie cron/z_bon_auto.php)
 * - Nachbestellung beim Lieferanten jeden Montag, Wareneingang nach 4–9 Tagen (neue Charge)
 *
 * Saison: Wolle im Winter, Baumwolle/Leinen im Sommer, Stoffe im Frühjahr; Umsatz wächst übers Jahr.
 */
class DemoVerkauf
{
    private const TAGE = 365;
    private const WOLLE    = [1 => 1.5, 1.3, 1.0, 0.8, 0.6, 0.5, 0.5, 0.6, 0.9, 1.3, 1.6, 1.8];
    private const SOMMER   = [1 => 0.4, 0.5, 0.9, 1.3, 1.7, 1.8, 1.7, 1.3, 0.8, 0.5, 0.4, 0.4];
    private const STOFF    = [1 => 0.9, 1.1, 1.4, 1.5, 1.3, 1.0, 0.8, 0.8, 1.1, 1.1, 1.0, 1.0];
    private const BESTELLT = [1 => 1.2, 1.1, 1.0, 0.9, 0.8, 0.7, 0.7, 0.8, 1.0, 1.2, 1.4, 1.6];

    private DokumentRepository $nummern;
    private array $artikel = [];          // id => Daten aus DemoArtikel
    private array $bestand = [];          // [artikelId][lagerId] => [[charge, menge], …] (FIFO)
    private array $eingaenge = [];        // 'Y-m-d' => [Bestellungen, die an dem Tag eintreffen]
    private array $kundenOnline = [], $kundenLaden = [], $haendler = [];
    private array $z = ['online' => 0, 'kasse' => 0, 'manuell' => 0, 'bestellung' => 0, 'widerruf' => 0];
    private int $widerrufBeantragt = 0;
    private int $shopNr = 1000;
    private int $kassierer, $buero, $jarvis;
    private float $jetzt;

    public function __construct(private DemoBasis $b)
    {
        $this->nummern = new DokumentRepository();
        foreach ($b->ids['verkauf'] as $a) $this->artikel[$a['id']] = $a;
        foreach ($b->ids['kunden'] as $k) {
            if ($k['haendler']) { $this->haendler[] = $k; continue; }
            if (in_array($k['herkunft'], ['kasse', 'messe'], true)) $this->kundenLaden[] = $k; else $this->kundenOnline[] = $k;
        }
        $this->kassierer = $b->ids['mitarbeiter'][1];
        $this->buero     = $b->ids['mitarbeiter'][0];
        $this->jarvis    = $b->jarvisId;
        $this->jetzt     = time();
    }

    public function erzeugen(): void
    {
        foreach ($this->b->db->query("SELECT artikel_id, lager_id, charge, bestand FROM lagerbestand ORDER BY id") as $r) {
            $this->bestand[(int)$r['artikel_id']][(int)$r['lager_id']][] = [$r['charge'], (float)$r['bestand']];
        }
        $this->b->db->beginTransaction();
        $this->b->ins('kassenbuch', ['typ' => 'anfangsbestand', 'betrag' => 200, 'notiz' => 'Wechselgeld', 'kasse_id' => $this->b->ids['kasse'],
            'benutzer_id' => $this->b->demoBenutzerId, 'erstellt_am' => date('Y-m-d 07:50:00', strtotime('-' . (self::TAGE + 1) . ' days'))]);
        for ($tag = self::TAGE; $tag >= 0; $tag--) $this->tag($tag);
        $this->bestandSchreiben();
        $this->b->db->commit();
        $this->b->log(sprintf('Verlauf: %d Online-, %d Telefon/Händler-Aufträge, %d Kassenbons, %d Bestellungen, %d Widerrufe',
            $this->z['online'], $this->z['manuell'], $this->z['kasse'], $this->z['bestellung'], $this->z['widerruf']));
        $this->zBons();
        $this->logbuch();
    }

    /**
     * Logbuch (Laufband unten + Admin → Aktivitäten) für die letzten 3 Tage so füllen,
     * wie es im echten Betrieb entsteht. Z-Bon-Einträge auf die Ladenschluss-Zeit setzen.
     */
    private function logbuch(): void
    {
        $db = $this->b->db;
        $db->exec("UPDATE aktivitaeten a JOIN kassen_abschluesse ka ON ka.id = a.referenz_id
                   SET a.erstellt_am = CONCAT(ka.datum, ' 18:35:00') WHERE a.aktion = 'kasse.z_bon'");
        $seit = date('Y-m-d', strtotime('-3 days'));
        $eintrag = fn(int $benutzer, string $aktion, string $tabelle, int $id, array $details, string $zeit) => $this->b->ins('aktivitaeten', [
            'benutzer_id' => $benutzer, 'aktion' => $aktion, 'referenz_tabelle' => $tabelle, 'referenz_id' => $id,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE), 'stufe' => 'info', 'erstellt_am' => $zeit]);
        $st = $db->prepare("SELECT id, bon_nr, zahlungsart, bruttobetrag, auftrag_id, erstellt_am FROM kassen_bons WHERE typ = 'verkauf' AND erstellt_am >= ?");
        $st->execute([$seit]);
        foreach ($st as $r) $eintrag($this->kassierer, 'kasse.bon.erstellt', 'kassen_bons', (int)$r['id'],
            ['bon_nr' => $r['bon_nr'], 'auftrag_id' => (int)$r['auftrag_id'], 'zahlungsart' => $r['zahlungsart'], 'bruttobetrag' => (float)$r['bruttobetrag']], $r['erstellt_am']);
        $st = $db->prepare("SELECT id, auftrag_nr, kanal, bruttobetrag, erstellt_am, versand_datum FROM auftraege WHERE kanal IN ('woocommerce', 'manuell') AND erstellt_am >= ?");
        $st->execute([$seit]);
        foreach ($st as $r) {
            $eintrag($r['kanal'] === 'manuell' ? $this->buero : $this->jarvis, 'auftraege.anlegen', 'auftraege', (int)$r['id'],
                ['auftrag_nr' => $r['auftrag_nr'], 'kanal' => $r['kanal'], 'brutto' => (float)$r['bruttobetrag']], $r['erstellt_am']);
            if ($r['versand_datum']) $eintrag($this->b->ids['mitarbeiter'][2], 'packplatz.versendet', 'auftraege', (int)$r['id'],
                ['auftrag_nr' => $r['auftrag_nr']], $r['versand_datum']);
        }
        $st = $db->prepare("SELECT auftrag_id, betrag, erfasst_am FROM auftrag_zahlungen WHERE erfasst_am >= ? AND zahlungsweg = 'ueberweisung' AND betrag > 0");
        $st->execute([$seit]);
        foreach ($st as $r) $eintrag($this->buero, 'auftraege.zahlung_buchen', 'auftraege', (int)$r['auftrag_id'],
            ['betrag' => (float)$r['betrag'], 'status' => 'bezahlt'], $r['erfasst_am']);
    }

    // ── Ein Tag ───────────────────────────────────────────────────────────

    private function tag(int $tag): void
    {
        $datum = date('Y-m-d', strtotime("-$tag days"));
        $wt = (int)date('N', strtotime($datum));
        $monat = (int)date('n', strtotime($datum));
        $f = self::BESTELLT[$monat] * (0.8 + 0.4 * (self::TAGE - $tag) / self::TAGE);   // Saison × Wachstum

        foreach ($this->eingaenge[$datum] ?? [] as $best) $this->wareneingang($best, $datum);
        if ($wt === 1) $this->nachbestellen($tag);

        $ereignisse = [];
        for ($i = $this->poisson(5.5 * $f); $i > 0; $i--) $ereignisse[] = ['online', $this->b->vorTagen($tag, 0, 23)];
        if ($wt <= 6) for ($i = $this->poisson(($wt === 6 ? 17 : 11) * $f); $i > 0; $i--)
            $ereignisse[] = ['kasse', $this->b->vorTagen($tag, 9, $wt === 6 ? 13 : 18)];
        if ($wt <= 5 && $this->b->chance(30)) $ereignisse[] = ['manuell', $this->b->vorTagen($tag, 9, 17)];
        if ((int)date('j', strtotime($datum)) === 5 && $wt <= 6) foreach ($this->haendler as $h) $ereignisse[] = ['haendler', $this->b->vorTagen($tag, 9, 12), $h];
        usort($ereignisse, fn($a, $b) => strcmp($a[1], $b[1]));   // Nummern laufen mit der Uhrzeit

        foreach ($ereignisse as $e) {
            match ($e[0]) {
                'online'   => $this->onlineAuftrag($e[1], $monat),
                'kasse'    => $this->kassenbon($e[1], $monat),
                'manuell'  => $this->rechnungsAuftrag($e[1], $monat, $this->b->eins($this->kundenLaden ?: $this->kundenOnline), false),
                'haendler' => $this->rechnungsAuftrag($e[1], $monat, $e[2], true),
            };
        }
    }

    // ── Online-Shop ───────────────────────────────────────────────────────

    private function onlineAuftrag(string $t0, int $monat): void
    {
        $shop = (int)$this->b->gewichtet([1 => 58, 2 => 26, 3 => 16]);
        $kunde = $this->stammkunde($this->kundenOnline);
        $za = $kunde['land'] === 'AT' ? $this->b->gewichtet(['paypal' => 55, 'vorkasse' => 35, 'nachnahme' => 10])
                                      : $this->b->gewichtet(['paypal' => 65, 'vorkasse' => 35]);
        $wunsch = $this->warenkorb($shop, $monat, $this->b->zufall(1, 4));
        if (!$wunsch) return;

        // Zahlung und Versand
        $bezahlt = match ($za) {
            'paypal'   => $t0,
            'vorkasse' => $this->b->chance(7) ? null : $this->plus($t0, mt_rand(8, 96) * 3600),
            default    => null,
        };
        if ($bezahlt && strtotime($bezahlt) > $this->jetzt) $bezahlt = null;
        $versand = ($za === 'nachnahme' || $bezahlt) ? $this->plus(max($t0, $bezahlt ?? $t0), mt_rand(4, 48) * 3600) : null;
        if ($versand && strtotime($versand) > $this->jetzt) $versand = null;
        if ($versand && $za === 'nachnahme') $bezahlt = $this->plus($versand, mt_rand(48, 120) * 3600);
        if ($bezahlt && strtotime($bezahlt) > $this->jetzt) $bezahlt = null;

        $positionen = $versand ? $this->ausLager($wunsch, $this->b->ids['lager']['zentral']) : $wunsch;
        if (!$positionen) return;

        $alter = (int)floor(($this->jetzt - strtotime($t0)) / 86400);
        $kuerzel = $this->kuerzel($shop);
        if ($versand) {
            [$liefer, $zahl] = $bezahlt ? ['abgeschlossen', 'bezahlt'] : ['versendet', 'ausstehend'];
        } elseif (!$bezahlt && $za === 'vorkasse' && $alter >= 30) {
            [$liefer, $zahl] = ['storniert', 'storniert'];
        } elseif (!$bezahlt && $za === 'vorkasse') {
            [$liefer, $zahl] = ['neu', 'ausstehend'];
        } else {
            [$liefer, $zahl] = [$alter === 0 ? 'neu' : $this->b->gewichtet(['in_bearbeitung' => 50, 'versandbereit' => 50]), $bezahlt ? 'bezahlt' : 'ausstehend'];
        }

        $a = $this->auftrag([
            'kanal' => 'woocommerce', 'shop_id' => $shop, 'kanal_auftrag_id' => ++$this->shopNr, 'kuerzel' => $kuerzel,
            'kunde' => $kunde, 'zahlungsart' => $za, 'zahlungsstatus' => $zahl, 'lieferstatus' => $liefer, 'erstellt_am' => $t0,
            'bezahlt_am' => $bezahlt, 'versand_datum' => $versand, 'erstellt_von' => $this->jarvis,
        ], $positionen, (bool)$versand);
        $this->z['online']++;

        if ($versand) $this->lagerAbgang($positionen, $this->b->ids['lager']['zentral'], $a['nr'], $versand);
        if ($versand) $this->rechnung($a, $versand, $kuerzel, 0);
        if ($bezahlt) $this->zahlung($a['id'], $a['brutto'], $bezahlt, ['paypal' => 'paypal', 'vorkasse' => 'ueberweisung', 'nachnahme' => 'nachnahme'][$za]);

        // Vorkasse ohne Zahlung: Erinnerung nach 14 Tagen, Storno nach 30 (wie cron/mahnwesen.php)
        if (!$bezahlt && $za === 'vorkasse' && $alter >= 14) {
            $this->mahnung($a['id'], 'erinnerung', 'versendet', $this->plus($t0, 14 * 86400 + 6 * 3600), $a['brutto'], $kunde['email']);
            if ($liefer === 'storniert') $this->mahnung($a['id'], 'stornierung', 'versendet', $this->plus($t0, 30 * 86400 + 6 * 3600), $a['brutto'], $kunde['email']);
        }
        // Widerruf: alt → abgewickelt (Rechnungskorrektur + Rückerstattung), ganz jung → beantragt
        if ($versand && $bezahlt && $alter > 25 && $this->b->chance(1.5)) $this->widerruf($a, $versand, $kuerzel);
        elseif ($versand && $alter <= 8 && $this->widerrufBeantragt < 2 && $this->b->chance(4)) {
            $this->b->db->prepare("UPDATE auftraege SET widerruf_status = 'beantragt', widerruf_am = ? WHERE id = ?")
                ->execute([$this->plus($versand, 2 * 86400), $a['id']]);
            $this->widerrufBeantragt++;
        }
    }

    // ── Telefon- und Händleraufträge auf Rechnung ─────────────────────────

    private function rechnungsAuftrag(string $t0, int $monat, array $kunde, bool $haendler): void
    {
        $wunsch = $this->warenkorb(1, $monat, $haendler ? $this->b->zufall(4, 8) : $this->b->zufall(1, 3), $haendler ? 4 : 1);
        if (!$wunsch) return;
        $versand = $this->plus($t0, mt_rand(18, 52) * 3600);
        if (strtotime($versand) > $this->jetzt) $versand = null;
        $positionen = $versand ? $this->ausLager($wunsch, $this->b->ids['lager']['zentral']) : $wunsch;
        if (!$positionen) return;
        if ($haendler) foreach ($positionen as &$p) $p['rabatt'] = 30; unset($p);

        $ziel = $haendler ? 30 : 14;
        $spaet = $this->b->chance($haendler ? 10 : 15);
        $nie   = !$haendler && $this->b->chance(6);   // zahlt gar nicht → Mahnwesen hat etwas zum Freigeben
        $bezahlt = $versand && !$nie ? $this->plus($versand, ($spaet ? mt_rand($ziel + 5, $ziel + 40) : mt_rand(3, $ziel)) * 86400) : null;
        if ($bezahlt && strtotime($bezahlt) > $this->jetzt) $bezahlt = null;

        $a = $this->auftrag([
            'kanal' => 'manuell', 'shop_id' => 1, 'kuerzel' => '', 'kunde' => $kunde, 'zahlungsart' => 'rechnung',
            'zahlungsbedingung_id' => $haendler ? 3 : 2,
            'zahlungsstatus' => $bezahlt ? 'bezahlt' : 'ausstehend',
            'lieferstatus' => $versand ? ($bezahlt ? 'abgeschlossen' : 'versendet') : 'in_bearbeitung',
            'erstellt_am' => $t0, 'bezahlt_am' => $bezahlt, 'versand_datum' => $versand, 'erstellt_von' => $this->buero,
            'versand_frei' => $haendler,
        ], $positionen, (bool)$versand);
        $this->z['manuell']++;
        if (!$versand) return;

        $this->lagerAbgang($positionen, $this->b->ids['lager']['zentral'], $a['nr'], $versand);
        $faellig = date('Y-m-d', strtotime($versand) + $ziel * 86400);
        $this->rechnung($a, $versand, '', $ziel);
        if ($bezahlt) { $this->zahlung($a['id'], $a['brutto'], $bezahlt, 'ueberweisung'); return; }

        // Offener Posten: Erinnerung 7 Tage nach Fälligkeit, 1. Mahnung 14 Tage danach als Vorschlag
        $tageUeber = (int)floor(($this->jetzt - strtotime($faellig)) / 86400);
        if ($tageUeber >= 7) {
            $this->mahnung($a['id'], 'erinnerung', 'versendet', $this->plus($faellig . ' 06:00:00', 7 * 86400), $a['brutto'], $kunde['email']);
            $this->b->db->prepare("UPDATE auftraege SET mahnung_stufe = 0, mahnung_gesendet_am = ? WHERE id = ?")
                ->execute([$this->plus($faellig . ' 06:00:00', 7 * 86400), $a['id']]);
        }
        if ($tageUeber >= 21) $this->mahnung($a['id'], 'mahnung1', 'vorgeschlagen', $this->plus($faellig . ' 06:00:00', 21 * 86400), $a['brutto'], $kunde['email']);
    }

    // ── Laden-Kasse ───────────────────────────────────────────────────────

    private function kassenbon(string $t, int $monat): void
    {
        $laden = $this->b->ids['lager']['laden'];
        $positionen = $this->ausLager($this->warenkorb(0, $monat, (int)$this->b->gewichtet([1 => 40, 2 => 30, 3 => 20, 4 => 10])), $laden);
        if (!$positionen) return;
        $kunde = $this->b->chance(30) && $this->kundenLaden ? $this->stammkunde($this->kundenLaden) : null;
        $jahr = (int)substr($t, 0, 4);
        $this->ids['bon'][$jahr] = ($this->ids['bon'][$jahr] ?? 0) + 1;
        $bonNr = 'K1-' . $jahr . '-' . str_pad((string)$this->ids['bon'][$jahr], 6, '0', STR_PAD_LEFT);

        $bonPos = []; $netto = 0.0; $brutto = 0.0;
        foreach ($positionen as $p) {
            $a = $this->artikel[$p['id']];
            $bonPos[] = ['menge' => $p['menge'], 'einzelpreis_brutto' => $a['vk'], 'steuer_prozent' => $a['steuer'], 'rabatt_prozent' => 0];
            $zeile = round($a['vk'] * $p['menge'], 2);
            $brutto += $zeile; $netto += $zeile / (1 + $a['steuer'] / 100);
        }
        $brutto = round($brutto, 2);
        $steuer = BfrService::steuerGruppenAusPositionen($bonPos);
        $bar = $this->b->chance(52);
        $gegeben = $bar ? $this->gegeben($brutto) : null;

        $bonId = $this->b->ins('kassen_bons', [
            'bon_nr' => $bonNr, 'typ' => 'verkauf', 'kasse_id' => $this->b->ids['kasse'], 'kunden_id' => $kunde['id'] ?? null,
            'zahlungsart' => $bar ? 'bar' : 'karte_extern', 'bruttobetrag' => $brutto, 'fremd_betrag' => 0, 'spende_betrag' => 0, 'spende_bar' => 0,
            'gegeben' => $gegeben, 'rueckgeld' => $bar ? round($gegeben - $brutto, 2) : null,
            'bar_betrag' => $bar ? $brutto : null, 'karten_betrag' => $bar ? null : $brutto,
            'steuer_a' => $steuer['a'], 'steuer_b' => $steuer['b'], 'steuer_c' => $steuer['c'], 'steuer_d' => $steuer['d'], 'steuer_e' => $steuer['e'],
            'benutzer_id' => $this->kassierer, 'erstellt_am' => $t, 'storniert' => 0, 'gedruckt' => 1,
        ]);
        foreach ($positionen as $i => $p) {
            $a = $this->artikel[$p['id']];
            $this->b->ins('kassen_bon_positionen', ['bon_id' => $bonId, 'artikel_id' => $p['id'], 'artikel_gruppe_id' => $a['gruppe'],
                'bezeichnung' => $a['name'], 'menge' => $p['menge'], 'einzelpreis_brutto' => $a['vk'], 'rabatt_prozent' => 0,
                'steuer_prozent' => $a['steuer'], 'charge' => $p['charge'], 'sort_order' => $i]);
        }
        // Spiegel-Auftrag wie KassenService::erstelleBon (Kanal kasse, Nummer = Bon-Nummer)
        $auftragId = $this->b->ins('auftraege', [
            'auftrag_nr' => $bonNr, 'kunden_id' => $kunde['id'] ?? null,
            'kunden_snapshot' => $kunde ? null : json_encode(['name' => 'Laufkunde', 'kundennummer' => '-'], JSON_UNESCAPED_UNICODE),
            'kanal' => 'kasse', 'zahlungsstatus' => 'bezahlt', 'lieferstatus' => 'abgeschlossen', 'zahlungsart' => $bar ? 'bar' : 'karte',
            'nettobetrag' => round($netto, 2), 'steuerbetrag' => round($brutto - $netto, 2), 'bruttobetrag' => $brutto,
            'bezahlt_am' => $t, 'versand_datum' => $t, 'lieferart' => 'abholung', 'erstellt_am' => $t, 'erstellt_von' => $this->kassierer,
        ]);
        foreach ($positionen as $i => $p) {
            $a = $this->artikel[$p['id']];
            $nettoEp = round($a['vk'] / (1 + $a['steuer'] / 100), 4);
            $this->b->ins('auftrag_positionen', ['auftrag_id' => $auftragId, 'artikel_id' => $p['id'], 'bezeichnung' => $a['name'],
                'menge' => (int)$p['menge'], 'einzelpreis_netto' => $nettoEp, 'steuer_prozent' => $a['steuer'], 'rabatt_prozent' => 0,
                'gesamtpreis_netto' => round($p['menge'] * $nettoEp, 2)]);
        }
        $this->b->db->prepare("UPDATE kassen_bons SET auftrag_id = ? WHERE id = ?")->execute([$auftragId, $bonId]);
        $this->lagerAbgang($positionen, $laden, 'Kassenbon ' . $bonNr, $t);
        $this->z['kasse']++;
    }

    /** Z-Bons über den echten KassenService (wie cron/z_bon_auto.php), heute nicht */
    private function zBons(): void
    {
        $_SESSION = ['benutzer' => ['id' => $this->jarvis]];
        $svc = new KassenService();
        $n = 0;
        foreach ($svc->offeneZTage($this->b->ids['kasse'], date('Y-m-d')) as $tag) {
            $r = $svc->erstelleZBon($this->b->ids['kasse'], $this->jarvis, $tag);
            if (!$r['erfolg']) { $this->b->log('Z-Bon ' . $tag . ': ' . $r['fehler']); break; }
            $n++;
        }
        // Z-Bons sollen zur Tageszeit des Ladenschlusses stehen, nicht „jetzt“
        $this->b->db->exec("UPDATE kassen_bons kb JOIN kassen_abschluesse ka ON ka.bon_id = kb.id
                            SET kb.erstellt_am = CONCAT(ka.datum, ' 18:35:00'), ka.erstellt_am = CONCAT(ka.datum, ' 18:35:00')
                            WHERE kb.typ = 'z_bon'");
        $this->b->log("Z-Bons: $n");
    }

    // ── Einkauf ───────────────────────────────────────────────────────────

    /** Jeden Montag: was unter den Meldebestand fällt, beim Lieferanten nachbestellen */
    private function nachbestellen(int $tag): void
    {
        $datum = date('Y-m-d', strtotime("-$tag days"));
        $jeLieferant = [];
        foreach ($this->artikel as $id => $a) {
            $stoff = $a['typ'] === 3;
            foreach (['zentral' => [$stoff ? 1500 : 12, $stoff ? 5000 : 35], 'laden' => [$stoff ? 400 : 4, $stoff ? 1500 : 12]] as $lager => [$min, $ziel]) {
                $lagerId = $this->b->ids['lager'][$lager];
                $ist = $this->summe($id, $lagerId);
                $ziel *= $a['renner'];
                if ($ist < $min * $a['renner']) {
                    $liefer = (int)$this->b->db->query("SELECT lieferant_id FROM artikel_lieferanten WHERE artikel_id = $id")->fetchColumn();
                    $jeLieferant[$liefer][] = ['id' => $id, 'lager' => $lagerId, 'menge' => $stoff ? (int)ceil(($ziel - $ist) / 100) * 100 : (int)($ziel - $ist)];
                }
            }
        }
        foreach ($jeLieferant as $lieferant => $zeilen) {
            $ankunft = date('Y-m-d', strtotime($datum) + mt_rand(4, 9) * 86400);
            $kommt = strtotime($ankunft) <= strtotime('today');
            $bestId = $this->b->ins('bestellungen', ['lieferant_id' => $lieferant, 'typ' => 'normal',
                'status' => $kommt ? 'erledigt' : $this->b->gewichtet(['offen' => 80, 'entwurf' => 20]), 'bestelldatum' => $datum,
                'erwartet_am' => date('Y-m-d', strtotime($datum) + 7 * 86400), 'lieferzeit_text' => 'ca. 1 Woche', 'zahlungsart' => 'Rechnung',
                'benutzer_id' => $this->buero, 'erstellt_am' => $datum . ' 09:15:00']);
            $summe = 0.0;
            foreach ($zeilen as &$z) {
                $ek = $this->artikel[$z['id']]['ek'];
                $z['pos'] = $this->b->ins('bestellung_positionen', ['bestellung_id' => $bestId, 'artikel_id' => $z['id'],
                    'menge_bestellt' => $z['menge'], 'menge_eingegangen' => 0, 'ek_preis' => $ek]);
                $summe += $ek * $z['menge'];
            }
            unset($z);
            $this->z['bestellung']++;
            if ($kommt) $this->eingaenge[$ankunft][] = ['id' => $bestId, 'lieferant' => $lieferant, 'zeilen' => $zeilen, 'summe' => $summe];
        }
    }

    private function wareneingang(array $best, string $datum): void
    {
        $zeit = $datum . ' 10:' . str_pad((string)mt_rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00';
        foreach ($best['zeilen'] as $z) {
            $a = $this->artikel[$z['id']];
            $charge = $a['charge_pflicht'] ? 'C' . mt_rand(1000, 9899) : null;
            $vorher = $this->summeCharge($z['id'], $z['lager'], $charge);
            $this->bestand[$z['id']][$z['lager']][] = [$charge, (float)$z['menge']];
            $bewId = $this->b->ins('lager_bewegungen', ['artikel_id' => $z['id'], 'lager_id' => $z['lager'], 'lieferant_id' => $best['lieferant'],
                'ek_preis' => $a['ek'], 'charge' => $charge, 'bewegungstyp' => 'eingang', 'menge' => $z['menge'],
                'bestand_vorher' => $vorher, 'bestand_nachher' => $vorher + $z['menge'], 'referenz' => 'Bestellung #' . $best['id'],
                'erstellt_am' => $zeit, 'benutzer_id' => $this->b->ids['mitarbeiter'][2]]);
            $this->b->ins('bestellung_eingaenge', ['position_id' => $z['pos'], 'bewegung_id' => $bewId, 'menge' => $z['menge'],
                'charge' => $charge, 'lager_id' => $z['lager'], 'benutzer_id' => $this->b->ids['mitarbeiter'][2], 'erstellt_am' => $zeit]);
            $this->b->db->prepare("UPDATE bestellung_positionen SET menge_eingegangen = ? WHERE id = ?")->execute([$z['menge'], $z['pos']]);
        }
        $betrag = round($best['summe'] * 1.2, 2);
        $this->b->db->prepare("UPDATE bestellungen SET ls_nummer = ?, rechnung_nummer = ?, rechnung_betrag = ?, rechnung_datum = ? WHERE id = ?")
            ->execute(['LS-' . mt_rand(10000, 99999), 'RE-' . mt_rand(100000, 999999), $betrag, $datum, $best['id']]);
        // Lieferantenrechnung innerhalb des Zahlungsziels (30 Tage) bezahlen, sofern das schon war
        $bezahlt = date('Y-m-d', strtotime($datum) + mt_rand(8, 28) * 86400);
        if (strtotime($bezahlt) <= strtotime('today')) {
            $this->b->ins('bestellung_zahlungen', ['bestellung_id' => $best['id'], 'betrag' => $betrag, 'art' => 'ueberweisung',
                'buchungsdatum' => $bezahlt, 'erfasst_am' => $bezahlt . ' 09:30:00', 'erfasst_von' => $this->buero]);
        }
    }

    // ── Bausteine Auftrag / Rechnung / Zahlung ────────────────────────────

    /** Legt Auftrag + Positionen an. $geliefert: Mengen sind ausgeliefert und werden verrechnet. */
    private function auftrag(array $d, array $positionen, bool $geliefert): array
    {
        $jahr = (int)substr($d['erstellt_am'], 0, 4);
        $nr = $this->nummern->naechsteNummer('auftrag', $jahr, $d['kuerzel']);
        $k = $d['kunde'];
        $adresse = ['name' => $k['firma'] ?: $k['vorname'] . ' ' . $k['nachname'], 'vorname' => $k['vorname'], 'nachname' => $k['nachname'],
                    'firma' => $k['firma'] ?? '', 'strasse' => $k['strasse'], 'plz' => $k['plz'], 'ort' => $k['ort'], 'land' => $k['land']];
        $snapshot = $adresse + ['email' => $k['email'], 'telefon' => '', 'kundennummer' => $k['kundennummer']];

        $netto = 0.0; $brutto = 0.0;
        foreach ($positionen as $p) {
            $a = $this->artikel[$p['id']];
            $nettoEp = round($a['vk'] / (1 + $a['steuer'] / 100), 4);
            $netto  += round($p['menge'] * $nettoEp * (1 - ($p['rabatt'] ?? 0) / 100), 2);
            $brutto += round(round($a['vk'], 2) * $p['menge'] * (1 - ($p['rabatt'] ?? 0) / 100), 2);
        }
        $versand = !empty($d['versand_frei']) || ($brutto >= 60 && $k['land'] === 'AT') ? 0.0 : ($k['land'] === 'AT' ? 4.90 : 9.90);
        $netto += $versand / 1.2; $brutto += $versand;

        $id = $this->b->ins('auftraege', [
            'auftrag_nr' => $nr, 'kunden_id' => $k['id'],
            'kunden_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'lieferadresse_snapshot' => json_encode($adresse, JSON_UNESCAPED_UNICODE),
            'rechnungsadresse_snapshot' => json_encode($adresse, JSON_UNESCAPED_UNICODE),
            'kanal' => $d['kanal'], 'shop_id' => $d['shop_id'], 'kanal_auftrag_id' => $d['kanal_auftrag_id'] ?? null,
            'zahlungsstatus' => $d['zahlungsstatus'], 'lieferstatus' => $d['lieferstatus'], 'zahlungsart' => $d['zahlungsart'],
            'zahlungsbedingung_id' => $d['zahlungsbedingung_id'] ?? null, 'versandkosten' => $versand,
            'versand_datum' => $d['versand_datum'], 'nettobetrag' => round($netto, 2), 'steuerbetrag' => round($brutto - $netto, 2),
            'bruttobetrag' => round($brutto, 2), 'bezahlt_am' => $d['zahlungsstatus'] === 'bezahlt' ? $d['bezahlt_am'] : null, 'mahnung_stufe' => 0,
            'tracking_nr' => $d['versand_datum'] ? '1' . mt_rand(10000000, 99999999) . mt_rand(1000, 9999) : null,
            'versanddienstleister' => $d['versand_datum'] ? 'post_at' : null,
            'erstellt_am' => $d['erstellt_am'], 'erstellt_von' => $d['erstellt_von'], 'lieferart' => 'versand',
            'versandklasse_id' => $k['land'] === 'AT' ? 1 : 4,
        ]);
        $posIds = [];
        foreach ($positionen as $i => $p) {
            $a = $this->artikel[$p['id']];
            $nettoEp = round($a['vk'] / (1 + $a['steuer'] / 100), 4);
            $posIds[] = $this->b->ins('auftrag_positionen', ['auftrag_id' => $id, 'artikel_id' => $p['id'], 'charge' => $p['charge'] ?? null,
                'bezeichnung' => $a['name'], 'menge' => (int)$p['menge'], 'menge_geliefert' => $geliefert ? (int)$p['menge'] : 0,
                'menge_abgeholt' => 0, 'menge_retourniert' => 0, 'menge_gutgeschrieben' => 0, 'menge_verrechnet' => $geliefert ? (int)$p['menge'] : 0,
                'einzelpreis_netto' => $nettoEp, 'steuer_prozent' => $a['steuer'], 'rabatt_prozent' => $p['rabatt'] ?? 0, 'preisbasis' => 'brutto',
                'gesamtpreis_netto' => round($p['menge'] * $nettoEp * (1 - ($p['rabatt'] ?? 0) / 100), 2), 'sort_order' => $i]);
        }
        return ['id' => $id, 'nr' => $nr, 'netto' => round($netto, 2), 'brutto' => round($brutto, 2), 'versand' => $versand,
                'positionen' => $positionen, 'pos_ids' => $posIds];
    }

    /** Rechnung über den ganzen Auftrag am Versandtag (PDF erzeugt der Download bei Bedarf aus der Datenbank) */
    private function rechnung(array $a, string $datum, string $kuerzel, int $zielTage): void
    {
        $jahr = (int)substr($datum, 0, 4);
        $nr = $this->nummern->naechsteNummer('rechnung', $jahr, $kuerzel);
        $rid = $this->b->ins('rechnungen', ['rechnung_nr' => $nr, 'auftrag_id' => $a['id'], 'nettobetrag' => $a['netto'],
            'steuerbetrag' => round($a['brutto'] - $a['netto'], 2), 'bruttobetrag' => $a['brutto'], 'versandkosten_brutto' => $a['versand'],
            'faellig_am' => date('Y-m-d', strtotime($datum) + $zielTage * 86400), 'leistungsdatum' => substr($datum, 0, 10),
            'dateiname' => $nr . '.pdf', 'storniert' => 0, 'erstellt_am' => $datum, 'erstellt_von' => $this->jarvis]);
        foreach ($a['positionen'] as $i => $p) {
            $art = $this->artikel[$p['id']];
            $nettoEp = round($art['vk'] / (1 + $art['steuer'] / 100), 4);
            $zn = round($p['menge'] * $nettoEp * (1 - ($p['rabatt'] ?? 0) / 100), 2);
            $zb = round($art['vk'] * $p['menge'] * (1 - ($p['rabatt'] ?? 0) / 100), 2);
            $this->b->ins('rechnung_positionen', ['rechnung_id' => $rid, 'auftrag_position_id' => $a['pos_ids'][$i], 'artikel_id' => $p['id'],
                'bezeichnung' => $art['name'], 'menge' => (int)$p['menge'], 'einzelpreis_netto' => $nettoEp, 'steuer_prozent' => $art['steuer'],
                'rabatt_prozent' => $p['rabatt'] ?? 0, 'preisbasis' => 'brutto', 'netto' => $zn, 'steuer' => round($zb - $zn, 2), 'brutto' => $zb]);
        }
        $this->b->ins('auftrag_dokumente', ['auftrag_id' => $a['id'], 'typ' => 'rechnung', 'beleg_nr' => $nr, 'dateiname' => $nr . '.pdf',
            'erstellt_am' => $datum, 'erstellt_von' => $this->jarvis]);
        $this->letzteRechnung = $rid;
    }

    private int $letzteRechnung = 0;

    private function zahlung(int $auftragId, float $betrag, string $datum, string $weg, ?string $notiz = null): void
    {
        $this->b->ins('auftrag_zahlungen', ['auftrag_id' => $auftragId, 'betrag' => $betrag, 'buchungsdatum' => substr($datum, 0, 10),
            'zahlungsweg' => $weg, 'notiz' => $notiz, 'erfasst_am' => $datum, 'erfasst_von' => $weg === 'paypal' ? $this->jarvis : $this->buero]);
    }

    private function mahnung(int $auftragId, string $typ, string $status, string $zeit, float $offen, string $mail): void
    {
        $this->b->ins('mahnungen', ['auftrag_id' => $auftragId, 'typ' => $typ, 'status' => $status, 'vorgeschlagen_am' => $zeit,
            'gesendet_am' => $status === 'versendet' ? $zeit : null, 'mail_an' => $mail, 'offen_betrag' => $offen,
            'gebuehr' => $typ === 'mahnung1' ? 5 : 0, 'erstellt_von' => 'cronjob']);
    }

    /** Widerruf abgewickelt: Rechnungskorrektur (Vollstorno), Ware zurück ins Lager, Rückerstattung */
    private function widerruf(array $a, string $versand, string $kuerzel): void
    {
        $zurueck = $this->plus($versand, mt_rand(6, 12) * 86400);
        if (strtotime($zurueck) > $this->jetzt) return;
        $jahr = (int)substr($zurueck, 0, 4);
        $this->b->ins('gutschriften', ['gutschrift_nr' => $this->nummern->naechsteNummer('gutschrift', $jahr, $kuerzel), 'auftrag_id' => $a['id'],
            'rechnung_id' => $this->letzteRechnung, 'art' => 'vollstorno', 'grund' => 'Widerruf durch Kundin',
            'nettobetrag' => -$a['netto'], 'steuerbetrag' => -round($a['brutto'] - $a['netto'], 2), 'bruttobetrag' => -$a['brutto'],
            'versandkosten_brutto' => -$a['versand'], 'erstellt_am' => $zurueck, 'erstellt_von' => $this->buero]);
        $this->b->db->prepare("UPDATE auftrag_positionen SET menge_retourniert = menge, menge_gutgeschrieben = menge WHERE auftrag_id = ?")->execute([$a['id']]);
        $this->b->db->prepare("UPDATE auftraege SET widerruf_status = 'widerrufen', widerruf_am = ?, zahlungsstatus = 'erstattet' WHERE id = ?")
            ->execute([$this->plus($versand, 3 * 86400), $a['id']]);
        $this->zahlung($a['id'], -$a['brutto'], $this->plus($zurueck, 86400), 'ueberweisung', 'Rückerstattung');
        $zentral = $this->b->ids['lager']['zentral'];
        foreach ($a['positionen'] as $p) {
            $vorher = $this->summeCharge($p['id'], $zentral, $p['charge']);
            $this->bestand[$p['id']][$zentral][] = [$p['charge'], (float)$p['menge']];
            $this->b->ins('lager_bewegungen', ['artikel_id' => $p['id'], 'lager_id' => $zentral, 'charge' => $p['charge'], 'bewegungstyp' => 'eingang',
                'menge' => $p['menge'], 'bestand_vorher' => $vorher, 'bestand_nachher' => $vorher + $p['menge'], 'referenz' => 'Retoure ' . $a['nr'],
                'erstellt_am' => $zurueck, 'benutzer_id' => $this->b->ids['mitarbeiter'][2]]);
        }
        $this->z['widerruf']++;
    }

    // ── Lager ─────────────────────────────────────────────────────────────

    /** Wunschmengen aus dem Lager nehmen (FIFO je Charge); was fehlt, wird gekürzt. Liefert Positionen mit Charge. */
    private function ausLager(array $wunsch, int $lagerId): array
    {
        $ergebnis = [];
        foreach ($wunsch as $w) {
            $rest = $w['menge'];
            foreach ($this->bestand[$w['id']][$lagerId] ?? [] as $i => [$charge, $menge]) {
                if ($rest <= 0) break;
                if ($menge <= 0) continue;
                $m = min($rest, $menge);
                // Meterware nur in 10-cm-Schritten, sonst nur ganze Stück
                $m = $this->artikel[$w['id']]['typ'] === 3 ? floor($m / 10) * 10 : floor($m);
                if ($m <= 0) continue;
                $ergebnis[] = ['id' => $w['id'], 'menge' => (int)$m, 'charge' => $charge, '_idx' => $i];
                $rest -= $m;
                break;   // eine Charge je Position (Farbgleichheit!)
            }
        }
        return $ergebnis;
    }

    private function lagerAbgang(array $positionen, int $lagerId, string $referenz, string $zeit): void
    {
        foreach ($positionen as $p) {
            $i = $p['_idx'] ?? null;
            if ($i === null) continue;
            $vorher = $this->bestand[$p['id']][$lagerId][$i][1];
            $this->bestand[$p['id']][$lagerId][$i][1] = $vorher - $p['menge'];
            $this->b->ins('lager_bewegungen', ['artikel_id' => $p['id'], 'lager_id' => $lagerId, 'charge' => $p['charge'], 'bewegungstyp' => 'ausgang',
                'menge' => $p['menge'], 'bestand_vorher' => $vorher, 'bestand_nachher' => $vorher - $p['menge'], 'referenz' => $referenz,
                'erstellt_am' => $zeit, 'benutzer_id' => $this->jarvis]);
        }
    }

    private function summe(int $artikelId, int $lagerId): float
    {
        return array_sum(array_column($this->bestand[$artikelId][$lagerId] ?? [], 1));
    }

    private function summeCharge(int $artikelId, int $lagerId, ?string $charge): float
    {
        $s = 0.0;
        foreach ($this->bestand[$artikelId][$lagerId] ?? [] as [$c, $m]) if ($c === $charge) $s += $m;
        return $s;
    }

    /** Endstand je Artikel/Lager/Charge in lagerbestand schreiben (UNIQUE artikel+lager+charge) */
    private function bestandSchreiben(): void
    {
        $this->b->db->exec("DELETE FROM lagerbestand");
        foreach ($this->bestand as $artikelId => $lager) {
            foreach ($lager as $lagerId => $chargen) {
                $sum = [];
                foreach ($chargen as [$c, $m]) $sum[$c ?? ''] = ($sum[$c ?? ''] ?? 0) + $m;
                foreach ($sum as $c => $m) {
                    if ($m <= 0 && $c !== '') continue;   // leere Chargen ausblenden (wie die echte Lagerübersicht)
                    $this->b->ins('lagerbestand', ['artikel_id' => $artikelId, 'lager_id' => $lagerId, 'charge' => $c === '' ? null : $c,
                        'charge_status' => $c === '' ? 'unbekannt' : 'erfasst', 'bestand' => max(0, $m)]);
                }
            }
        }
    }

    // ── Warenkorb + Zufall ────────────────────────────────────────────────

    /** Zufälliger Warenkorb: Artikel nach Beliebtheit × Saison, Shop 0 = Laden (alles) */
    private function warenkorb(int $shop, int $monat, int $anzahl, int $mengenFaktor = 1): array
    {
        $gewichte = [];
        foreach ($this->artikel as $id => $a) {
            if ($shop && !in_array($shop, $a['shops'], true)) continue;
            $s = $a['saison'] === 'sommer' ? self::SOMMER[$monat] : ($a['typ'] === 1 ? self::WOLLE[$monat] : ($a['typ'] === 3 ? self::STOFF[$monat] : 1.0));
            $gewichte[$id] = (int)round($a['renner'] * $s * 10);
        }
        if (!$gewichte) return [];
        $korb = [];
        for ($i = 0; $i < $anzahl; $i++) {
            $id = (int)$this->b->gewichtet($gewichte);
            if (isset($korb[$id])) continue;
            $a = $this->artikel[$id];
            $menge = match ($a['typ']) {
                3 => mt_rand(5, 30) * 10,
                1 => (int)$this->b->gewichtet([1 => 18, 2 => 18, 3 => 14, 4 => 14, 5 => 12, 6 => 10, 8 => 8, 10 => 6]),
                default => $this->b->chance(85) ? 1 : 2,
            };
            $korb[$id] = ['id' => $id, 'menge' => $menge * $mengenFaktor];
        }
        return array_values($korb);
    }

    /** Stammkunden kaufen öfter: vordere Kunden der Liste werden bevorzugt */
    private function stammkunde(array $liste): array
    {
        $r = mt_rand(0, 9999) / 10000;   // < 1, sonst Index hinter dem Listenende
        return $liste[(int)floor(count($liste) * $r * $r)];
    }

    private function kuerzel(int $shop): string
    {
        return (string)$this->b->db->query("SELECT nr_kuerzel FROM shops WHERE id = $shop")->fetchColumn();
    }

    private function poisson(float $lambda): int
    {
        $l = exp(-$lambda); $k = 0; $p = 1.0;
        do { $k++; $p *= mt_rand() / mt_getrandmax(); } while ($p > $l);
        return $k - 1;
    }

    private function plus(string $zeit, int $sekunden): string
    {
        return date('Y-m-d H:i:s', strtotime($zeit) + $sekunden);
    }

    /** Was Kundinnen an der Kasse hergeben: passend oder nächster runder Schein */
    private function gegeben(float $betrag): float
    {
        if ($this->b->chance(25)) return $betrag;
        foreach ([5, 10, 20, 50, 100, 200] as $schein) if ($schein >= $betrag) return (float)$schein;
        return ceil($betrag / 50) * 50;
    }

    private array $ids = [];
}
