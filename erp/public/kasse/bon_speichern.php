<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/modules/kasse/MesseSyncService.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';
require_once __DIR__ . '/../../src/modules/arbeitsplatz/ArbeitsplatzService.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/core/Mailer.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragRepository.php';
require_once __DIR__ . '/../../src/modules/auftraege/Positionsrechnung.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragAbschluss.php';
require_once __DIR__ . '/../../src/modules/dokumente/DokumentService.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';
require_once __DIR__ . '/../../src/modules/packplatz/RuecklagerungRepository.php';
require_once __DIR__ . '/../../src/core/Logger.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt.']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Daten.']); exit;
}

// kasse_id wird bewusst NICHT aus dem Client-Payload genommen, sondern serverseitig
// über die Arbeitsplatz-Bindung der Session ermittelt — sonst könnte ein direkter POST
// (z.B. per Shortcut statt über bon.php) jede Sperre hier einfach umgehen.
$aktuelleKasseId = (new ArbeitsplatzService())->aktuelleKasseId();
if ($aktuelleKasseId === null) {
    // Kein sicherer Arbeitsplatz-Bezug (unbekanntes Gerät, Fallback auf Kasse 1 verboten
    // weil die BFR-aktiv ist) — auf keinen Fall verkaufen, sonst RKSV-Signatur-Risiko.
    echo json_encode(['erfolg' => false, 'fehler' => 'Dieses Gerät ist keiner Kasse zugeordnet. Bitte zuerst über die Kasse-Startseite einen Arbeitsplatz auswählen.']);
    exit;
}
if ((new MesseSyncService())->hatOffenenResync($aktuelleKasseId)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Diese Kasse hat noch offene Messe-Daten (Sync ausstehend) — bitte zuerst synchronisieren, bevor hier online verkauft wird.']);
    exit;
}

$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);
$service    = new KassenService();

$bonDaten = [
    'kasse_id'         => $aktuelleKasseId, // server-geprüfte Arbeitsplatz-Bindung, NICHT aus dem Client-Payload (siehe Kommentar oben)
    'lager_id'         => (int)($input['lager_id']         ?? 1),
    'zahlungsart'      => $input['zahlungsart']             ?? 'bar',
    'bruttobetrag'     => (float)($input['bruttobetrag']   ?? 0),
    'gegeben'          => isset($input['gegeben'])          ? (float)$input['gegeben'] : null,
    'rueckgeld'        => isset($input['rueckgeld'])        ? (float)$input['rueckgeld'] : null,
    'bar_betrag'       => isset($input['bar_betrag'])       ? (float)$input['bar_betrag'] : null,
    'karten_betrag'    => isset($input['karten_betrag'])    ? (float)$input['karten_betrag'] : null,
    'gutschein_code'   => $input['gutschein_code']          ?? null,
    'gutschein_betrag' => isset($input['gutschein_betrag']) ? (float)$input['gutschein_betrag'] : null,
    'auftrag_id'       => isset($input['auftrag_id'])       ? (int)$input['auftrag_id'] : null,
    'kunden_id'        => isset($input['kunden_id'])        ? (int)$input['kunden_id'] : null,
    'notiz'            => $input['notiz']                   ?? null,
];

$positionen = $input['positionen'] ?? [];
if (empty($positionen)) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Keine Positionen.']); exit;
}

// Positionen bereinigen
$sauberePositionen = [];
foreach ($positionen as $p) {
    $sauberePositionen[] = [
        'artikel_id'          => isset($p['artikel_id']) && $p['artikel_id'] ? (int)$p['artikel_id'] : null,
        // Erlöskonto-Gruppe nur für Divers (ohne artikel_id) — echte Artikel bringen ihre Gruppe selbst mit
        'artikel_gruppe_id'   => empty($p['artikel_id']) && !empty($p['artikel_gruppe_id']) ? (int)$p['artikel_gruppe_id'] : null,
        'bezeichnung'         => trim($p['bezeichnung'] ?? ''),
        'ean'                 => $p['ean']   ?? null,
        'menge'               => (float)($p['menge'] ?? 1),
        'einzelpreis_brutto'  => (float)($p['einzelpreis_brutto'] ?? 0),
        'steuer_prozent'      => (float)($p['steuer_prozent'] ?? 20),
        'rabatt_prozent'      => (float)($p['rabatt_prozent'] ?? 0),
        'charge'                       => $p['charge'] ?? null,
        'konfig_wert_ids'              => !empty($p['konfig_wert_ids']) ? array_map('intval', $p['konfig_wert_ids']) : [],
        'nachzutragen_lagerbestand_id' => isset($p['nachzutragen_lagerbestand_id']) ? (int)$p['nachzutragen_lagerbestand_id'] : null,
        'block'               => !empty($p['vonAuftrag']) ? 'auftrag' : ($p['block'] ?? null),
        'auftrag_position_id' => isset($p['auftrag_position_id']) ? (int)$p['auftrag_position_id'] : null,
        'retour_von_position_id' => isset($p['retour_von_position_id']) ? (int)$p['retour_von_position_id'] : null,
        'web_auftrag_id'         => !empty($p['web_auftrag_id']) ? (int)$p['web_auftrag_id'] : null,
        'gutschein_empfaenger'   => trim((string)($p['gutschein_empfaenger'] ?? '')) ?: null,
        'zahlung_auftrag_id'     => !empty($p['zahlung_auftrag_id']) ? (int)$p['zahlung_auftrag_id'] : null,
        'guthaben'               => !empty($p['guthaben']),
    ];
}

// Konfigurator-Positionen: Preis IMMER serverseitig aus den gewählten Werten neu berechnen,
// der vom Client mitgeschickte Preis wird verworfen (gleiche Philosophie wie der
// Bruttobetrag-Check direkt darunter, nur eine Ebene tiefer). Zusätzlich kein Lagerabzug --
// Konfigurationsartikel (Schilder etc.) werden auf Bestellung gefertigt, siehe
// artikel.keine_lagerbestandsfuehrung.
require_once __DIR__ . '/../../src/modules/konfigurator/KonfiguratorService.php';
$konfigSvc = new KonfiguratorService();
foreach ($sauberePositionen as &$p) {
    if (empty($p['konfig_wert_ids'])) continue;
    $r = $konfigSvc->berechnePreis((int)$p['artikel_id'], $p['konfig_wert_ids']);
    if (!$r['erfolg']) {
        echo json_encode(['erfolg' => false, 'fehler' => 'Konfigurator: ' . implode(', ', $r['fehler'])]);
        exit;
    }
    $p['einzelpreis_brutto'] = $r['brutto'];
    $p['steuer_prozent']     = $r['steuer_prozent'];
    $p['kein_lagerabzug']    = true;
}
unset($p);

// Artikel ohne eigenen Lagerbestand (Gutschein-Artikel, Typ ohne Lagerstand, "keine
// Lagerbestandsführung") nie vom Lager abbuchen -- das kein_lagerabzug-Flag aus dem
// Client wird oben beim Bereinigen bewusst verworfen, deshalb serverseitig aus den
// Stammdaten bestimmen. Gutschein-Kauf-Positionen (block 'gutschein_kauf') zusätzlich
// absichern: nur echter Gutschein-Artikel, 0% MwSt (Mehrzweckgutschein), kein Rabatt,
// ganze Stückzahl -- jeder Stück wird ein eigener Code.
$posArtikelIds = array_values(array_unique(array_filter(array_column($sauberePositionen, 'artikel_id'))));
$ohneLagerIds = [];
$gutscheinArtikelIds = [];
if ($posArtikelIds) {
    $ph = implode(',', array_fill(0, count($posArtikelIds), '?'));
    $stmtOL = Database::getInstance()->prepare("
        SELECT a.id, a.ist_gutschein
        FROM artikel a
        JOIN artikel_typen at ON at.id = a.artikeltyp_id
        WHERE a.id IN ($ph)
          AND (a.ist_gutschein = 1 OR a.keine_lagerbestandsfuehrung = 1 OR at.hat_lagerstand = 0)
    ");
    $stmtOL->execute($posArtikelIds);
    foreach ($stmtOL->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $ohneLagerIds[(int)$r['id']] = true;
        if ((int)$r['ist_gutschein'] === 1) $gutscheinArtikelIds[(int)$r['id']] = true;
    }
}
foreach ($sauberePositionen as &$p) {
    if (!empty($p['artikel_id']) && isset($ohneLagerIds[$p['artikel_id']])) {
        $p['kein_lagerabzug'] = true;
    }
    if ($p['block'] === 'gutschein_kauf') {
        if (empty($p['artikel_id']) || !isset($gutscheinArtikelIds[$p['artikel_id']])) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Gutschein-Position ohne gültigen Gutschein-Artikel.']); exit;
        }
        if ($p['einzelpreis_brutto'] <= 0 || $p['menge'] < 1 || floor($p['menge']) != $p['menge']) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Gutschein: Betrag und Stückzahl müssen größer als 0 sein.']); exit;
        }
        $p['einzelpreis_brutto'] = round($p['einzelpreis_brutto'], 2);
        $p['rabatt_prozent']     = 0;
        $p['steuer_prozent']     = 0;
    }
}
unset($p);
$gutscheinKaufPositionen = array_values(array_filter($sauberePositionen, fn($p) => $p['block'] === 'gutschein_kauf'));

// ── Zahlbeleg: Zahlung auf eine bestehende Rechnung (Belege-Umbau 2026-10-07) ──
// 0 % (USt steht schon auf der Rechnung), kein Lager, Betrag höchstens der offene
// Rechnungsbetrag -- alles serverseitig festgelegt, nicht vom Client übernommen.
$zahlungPositionen = [];
foreach ($sauberePositionen as $i => &$p) {
    if ($p['block'] !== 'zahlung') continue;
    $aidZ = (int)($p['zahlung_auftrag_id'] ?? 0);
    $az = Database::getInstance()->prepare("SELECT id, auftrag_nr FROM auftraege WHERE id = ? AND lieferstatus <> 'storniert'");
    $az->execute([$aidZ]);
    $zAuftrag = $az->fetch(PDO::FETCH_ASSOC);
    if (!$zAuftrag) { echo json_encode(['erfolg' => false, 'fehler' => 'Zahlbeleg: Auftrag nicht gefunden.']); exit; }
    $offenZ = (new DokumentService())->offenerRechnungsbetrag($aidZ);
    $betragZ = round($p['einzelpreis_brutto'] * $p['menge'], 2);
    if ($betragZ <= 0 || $betragZ > $offenZ + 0.005) {
        echo json_encode(['erfolg' => false, 'fehler' => 'Zahlbeleg ' . $zAuftrag['auftrag_nr'] . ': höchstens € '
            . number_format(max(0, $offenZ), 2, ',', '.') . ' offen.']); exit;
    }
    $reNr = Database::getInstance()->prepare("SELECT GROUP_CONCAT(rechnung_nr ORDER BY id SEPARATOR ', ') FROM rechnungen WHERE auftrag_id = ? AND storniert = 0");
    $reNr->execute([$aidZ]);
    $p['artikel_id']         = null;
    $p['menge']              = 1;
    $p['einzelpreis_brutto'] = $betragZ;
    $p['steuer_prozent']     = 0;
    $p['rabatt_prozent']     = 0;
    $p['kein_lagerabzug']    = true;
    $p['bezeichnung']        = 'Zahlung zu Auftrag ' . $zAuftrag['auftrag_nr'] . (($r = $reNr->fetchColumn()) ? ' (Rechnung ' . $r . ')' : '');
    $zahlungPositionen[] = ['auftrag_id' => $aidZ, 'auftrag_nr' => $zAuftrag['auftrag_nr'], 'betrag' => $betragZ];
}
unset($p);

// Erstattung "will er nicht" bei vorab bezahlten Abholungen (Retour-Zeile mit web_auftrag_id,
// aber ohne retour_von_position_id): diese Ware wurde nie übergeben und nie verrechnet --
// zurückgezahlt wird eine Anzahlung, keine Erlösminderung. Deshalb 0 %, als Rückzahlung
// (block 'zahlung', negativ). Kassen-Retouren schon verrechneter Ware bleiben 'retour' mit
// Steuer (wirken wie eine Gutschrift). Belege-Umbau 2026-10-07.
foreach ($sauberePositionen as &$p) {
    // Guthaben eines bezahlten Auftrags (mehr bezahlt als der Auftragsbetrag, z.B. Versand →
    // Abholung umgestellt): höchstens das tatsächliche Guthaben, serverseitig nachgerechnet
    if ($p['guthaben'] && $p['block'] === 'retour' && !empty($p['web_auftrag_id'])) {
        $gh = Database::getInstance()->prepare("
            SELECT a.auftrag_nr, a.zahlungsstatus,
                   (SELECT COALESCE(SUM(betrag), 0) FROM auftrag_zahlungen WHERE auftrag_id = a.id) + a.gutschein_betrag - a.bruttobetrag
                   - (SELECT COALESCE(SUM(gebuehr), 0) FROM mahnungen WHERE auftrag_id = a.id AND status = 'versendet' AND gebuehr_erlassen_am IS NULL) AS guthaben
            FROM auftraege a WHERE a.id = ?
        ");
        $gh->execute([(int)$p['web_auftrag_id']]);
        $ghRow  = $gh->fetch(PDO::FETCH_ASSOC);
        if ($ghRow && AuftragAbschluss::saldoAussagekraeftig((int)$p['web_auftrag_id'])) {
            $ghRow['guthaben'] = -AuftragAbschluss::saldo((int)$p['web_auftrag_id']); // Saldo aus Belegen
        }
        $betrag = round(abs($p['menge'] * $p['einzelpreis_brutto']), 2);
        if (!$ghRow || $ghRow['zahlungsstatus'] !== 'bezahlt' || $betrag > round((float)$ghRow['guthaben'], 2) + 0.005) {
            echo json_encode(['erfolg' => false, 'fehler' => 'Guthaben-Auszahlung: höchstens € '
                . number_format(max(0, (float)($ghRow['guthaben'] ?? 0)), 2, ',', '.') . ' möglich — bitte Auftrag neu laden.']); exit;
        }
        $p['block'] = 'zahlung'; $p['menge'] = -1; $p['einzelpreis_brutto'] = $betrag;
        $p['rabatt_prozent'] = 0; $p['steuer_prozent'] = 0; $p['artikel_id'] = null; $p['kein_lagerabzug'] = true;
        $p['bezeichnung'] = 'Rückzahlung Guthaben zu Auftrag ' . $ghRow['auftrag_nr'];
        continue;
    }
    if ($p['block'] === 'retour' && !empty($p['web_auftrag_id']) && empty($p['retour_von_position_id'])) {
        $p['block']              = 'zahlung';
        $p['einzelpreis_brutto'] = round($p['einzelpreis_brutto'] * (1 - $p['rabatt_prozent'] / 100), 2);
        $p['rabatt_prozent']     = 0;
        $p['steuer_prozent']     = 0;
        $p['bezeichnung']        = 'Rückzahlung (nicht abgeholt): ' . $p['bezeichnung'];
        $p['artikel_id']         = null;
        $p['kein_lagerabzug']    = true;
    }
}
unset($p);

// Bruttobetrag serverseitig aus Positionen neu berechnen (kein Vertrauen auf Client-Wert)
$serverBrutto = 0;
foreach ($sauberePositionen as $p) {
    $serverBrutto += $p['menge'] * $p['einzelpreis_brutto'] * (1 - $p['rabatt_prozent'] / 100);
}
$bonDaten['bruttobetrag'] = round($serverBrutto, 2);

// ── Geladene Web-Aufträge ──────────────────────────────────────────────────────
// Sammelabholung (2026-10-01): mehrere Online-Aufträge EINES Kunden auf einem Bon.
// Payload 'web_auftraege' = [{id, mitnehmen}]; der alte Einzel-Payload (web_auftrag_id +
// web_auftrag_mitnehmen) wird weiter verstanden. Liefer- und Zahlungsstatus kommen aus
// der DB, nicht vom Client.
$webAuftragEingabe = [];
if (!empty($input['web_auftraege']) && is_array($input['web_auftraege'])) {
    foreach ($input['web_auftraege'] as $wa) {
        if (!empty($wa['id'])) {
            $webAuftragEingabe[(int)$wa['id']] = array_key_exists('mitnehmen', $wa) ? $wa['mitnehmen'] : null;
        }
    }
} elseif (!empty($input['web_auftrag_id'])) {
    $webAuftragEingabe[(int)$input['web_auftrag_id']] = $input['web_auftrag_mitnehmen'] ?? null;
}

/** @var array<int, array{id:int, auftrag:array, status:string, mitnehmen:?bool, bezahlt:bool}> $webAuftraege */
$webAuftraege = [];
if ($webAuftragEingabe) {
    $ph = implode(',', array_fill(0, count($webAuftragEingabe), '?'));
    $stmtWa = Database::getInstance()->prepare("SELECT * FROM auftraege WHERE id IN ($ph)");
    $stmtWa->execute(array_keys($webAuftragEingabe));
    $waZeilen = [];
    foreach ($stmtWa->fetchAll(PDO::FETCH_ASSOC) as $a) $waZeilen[(int)$a['id']] = $a;

    // Menge im Abholfach je Position: gepackt minus schon abgeholt. Ist ein Auftrag ohne
    // Packplatz auf "abholbereit" gesetzt worden (menge_geliefert 0), liegt die ganze Menge bereit.
    $fachVon = function (array $op, string $status): float {
        $geliefert = (float)$op['menge_geliefert'];
        if ($status === 'abholbereit' && $geliefert < 0.001) $geliefert = (float)$op['menge'];
        return max(0.0, $geliefert - (float)$op['menge_abgeholt']);
    };
    $fachStmt  = Database::getInstance()->prepare("SELECT COALESCE(SUM(GREATEST(CAST(menge_geliefert AS SIGNED) - CAST(menge_abgeholt AS SIGNED), 0)), 0) FROM auftrag_positionen WHERE auftrag_id = ?");
    $fachMenge = function (int $aid) use ($fachStmt): float { $fachStmt->execute([$aid]); return (float)$fachStmt->fetchColumn(); };

    $kundenSchluessel = [];
    foreach ($webAuftragEingabe as $aid => $mitnehmen) {
        $a = $waZeilen[$aid] ?? null;
        if (!$a || $a['lieferstatus'] === 'storniert') {
            echo json_encode(['erfolg' => false, 'fehler' => 'Ein geladener Auftrag existiert nicht mehr oder ist storniert — bitte neu laden.']); exit;
        }
        $snap = json_decode($a['kunden_snapshot'] ?? '{}', true) ?: [];
        $kundenSchluessel[] = $a['kunden_id'] ? 'k' . $a['kunden_id'] : 'm' . strtolower(trim($snap['email'] ?? ''));
        $webAuftraege[$aid] = [
            'id'        => $aid,
            'auftrag'   => $a,
            'status'    => $a['lieferstatus'],
            'mitnehmen' => $mitnehmen === null ? null : (bool)$mitnehmen,
            'bezahlt'   => $a['zahlungsstatus'] === 'bezahlt',
            // Abholfach: gepackte Ware liegt bereit (abholbereit, oder teilgeliefert mit Rest im
            // Fach nach "holt er später") → Übergabe ohne Lagerbuchung, siehe Migration 194
            'im_fach'   => $a['lieferstatus'] === 'abholbereit'
                || ($a['lieferstatus'] === 'teilgeliefert' && $a['lieferart'] === 'abholung' && $fachMenge((int)$aid) > 0),
        ];
    }
    // Mehrere Aufträge nur vom selben Kunden (gleiches Kundenkonto bzw. bei Gast-
    // Bestellungen gleiche E-Mail; Gast ohne E-Mail = 'm' -> nie zusammenfassbar)
    if (count($webAuftraege) > 1 && (count(array_unique($kundenSchluessel)) > 1 || in_array('m', $kundenSchluessel, true))) {
        echo json_encode(['erfolg' => false, 'fehler' => 'Sammelabholung geht nur für Aufträge desselben Kunden.']); exit;
    }
}
// Erster Auftrag = Haupt-Auftrag für kassen_bons.auftrag_id/web_auftrag_id (Altbestand)
$webAuftragId = $webAuftraege ? array_key_first($webAuftraege) : null;

$nurAbschliessen = ($input['nur_abschliessen'] ?? false) === true;

// Positionen ihrem Auftrag zuordnen: über auftrag_position_id bzw. retour_von_position_id
// aus der DB (maßgeblich), sonst die vom Client mitgegebene web_auftrag_id (Retour-Zeilen
// aus verringerten Abhol-Mengen). Freitext-Retouren und Extras bleiben ohne Auftrag.
$posIdsZuordnen = [];
foreach ($sauberePositionen as $p) {
    if ($p['auftrag_position_id'])    $posIdsZuordnen[] = $p['auftrag_position_id'];
    if ($p['retour_von_position_id']) $posIdsZuordnen[] = $p['retour_von_position_id'];
}
$posZuAuftrag = [];
if ($posIdsZuordnen) {
    $posIdsZuordnen = array_values(array_unique($posIdsZuordnen));
    $ph = implode(',', array_fill(0, count($posIdsZuordnen), '?'));
    $stmtPz = Database::getInstance()->prepare("SELECT id, auftrag_id FROM auftrag_positionen WHERE id IN ($ph)");
    $stmtPz->execute($posIdsZuordnen);
    $posZuAuftrag = array_map('intval', $stmtPz->fetchAll(PDO::FETCH_KEY_PAIR));
}
foreach ($sauberePositionen as &$p) {
    $pid = $p['auftrag_position_id'] ?: $p['retour_von_position_id'];
    if ($pid) {
        $p['web_auftrag_id'] = $posZuAuftrag[$pid] ?? null;
    }
    if ($p['web_auftrag_id'] && !isset($webAuftraege[$p['web_auftrag_id']])) {
        echo json_encode(['erfolg' => false, 'fehler' => 'Eine Bon-Position gehört zu einem Auftrag, der nicht geladen ist — bitte Auftrag neu laden.']); exit;
    }
}
unset($p);

// Sammelabholung: von einem Auftrag gar nichts mitgenommen (alle Mengen 0) → er bleibt
// unverändert liegen (Ware bleibt gepackt/reserviert, keine Zahlung, keine Erstattung).
// Seine Zeilen inkl. evtl. Retour-Zeilen fliegen ganz aus dem Bon. Beim EINZELNEN
// Auftrag bleibt es wie bisher (bezahlt + alles 0 = komplette Rückgabe).
if (count($webAuftraege) > 1) {
    foreach (array_keys($webAuftraege) as $aid) {
        $mengen = array_column(array_filter($sauberePositionen, fn($p) => $p['web_auftrag_id'] === $aid && !empty($p['auftrag_position_id'])), 'menge');
        if ($mengen && array_sum($mengen) < 0.001) {
            unset($webAuftraege[$aid]);
            $sauberePositionen = array_values(array_filter($sauberePositionen, fn($p) => $p['web_auftrag_id'] !== $aid));
        }
    }
    if (!$webAuftraege) {
        echo json_encode(['erfolg' => false, 'fehler' => 'Von keinem der Aufträge wird etwas mitgenommen.']); exit;
    }
    $webAuftragId = array_key_first($webAuftraege);
}

/** Positionen eines Auftrags (inkl. seiner Retour-Zeilen) */
$positionenVon = fn(int $aid): array => array_values(array_filter($sauberePositionen, fn($p) => $p['web_auftrag_id'] === $aid));

// "Rest will der Kunde nicht" (bon.php fragt je reduzierter Auftragszeile, Jacky 2026-10-02):
// diese Menge wird NICHT später abgeholt — zählt wie eine Kassen-Retoure (menge_retourniert,
// bei bezahlten Aufträgen auch menge_gutgeschrieben; das Geld läuft über die Retour-Zeile des
// Bons). Ohne Angabe bleibt der Rest offen (teilgeliefert, Kunde holt ihn später).
$restVerzicht = []; // [auftrag_id][auftrag_position_id] => menge
foreach ((array)($input['rest_verzicht'] ?? []) as $rv) {
    $pid   = (int)($rv['auftrag_position_id'] ?? 0);
    $menge = (float)($rv['menge'] ?? 0);
    $aidRv = $posZuAuftrag[$pid] ?? null;
    if ($pid && $menge > 0 && $aidRv && isset($webAuftraege[$aidRv])) {
        $restVerzicht[$aidRv][$pid] = $menge;
    }
}

// ── Manager-Override: Barauszahlung bei Retour eines bereits bezahlten Web-Auftrags ──
// Läuft VOR jeder Buchung (erstelleBon() folgt erst weiter unten), damit bei fehlender
// Freigabe wirklich nichts passiert — kein halb gebuchter Bon, keine Lagerbewegung.
// Rechnung je bezahltem Auftrag wie bisher beim Einzelauftrag, dann summiert.
if (!$nurAbschliessen) {
    $vorabRetourBetrag = 0.0;
    foreach ($webAuftraege as $aid => $wa) {
        if (!$wa['bezahlt']) continue;
        $vorabAuftragAnteil = 0.0;
        foreach ($positionenVon($aid) as $bp) {
            if (!empty($bp['auftrag_position_id'])) {
                $vorabAuftragAnteil += $bp['menge'] * $bp['einzelpreis_brutto'] * (1 - $bp['rabatt_prozent'] / 100);
            }
        }
        $vorabAuftragAnteil = round($vorabAuftragAnteil, 2);
        if ($vorabAuftragAnteil <= 0) {
            $vorabAuftragAnteil = (float)$wa['auftrag']['bruttobetrag'];
        }
        $vorabRetourBetrag += round((float)$wa['auftrag']['bruttobetrag'] - $vorabAuftragAnteil, 2);
    }
    // Guthaben-Auszahlung (Rückzahlungszeilen) braucht dieselbe Freigabe wie eine Retour-Auszahlung
    foreach ($sauberePositionen as $bp) {
        if ($bp['block'] === 'zahlung' && $bp['guthaben']) $vorabRetourBetrag += abs($bp['menge'] * $bp['einzelpreis_brutto']);
    }

    if ($vorabRetourBetrag > 0.005 && !Auth::kann('kasse.auszahlung')) {
        $manager = Auth::pruefeManagerPin((string)($input['manager_pin'] ?? ''));
        if (!$manager) {
            echo json_encode([
                'erfolg' => false,
                'fehler' => 'Auszahlung von € ' . number_format($vorabRetourBetrag, 2, ',', '.') . ' braucht eine Manager-Freigabe (PIN).',
                'braucht_manager_pin' => true,
            ]);
            exit;
        }
        Logger::log('manager_override', 'auftraege', $webAuftragId, [
            'ausgeloest_von'  => $benutzerId,
            'freigegeben_von' => $manager['id'],
            'kontext'         => 'kasse_auszahlung',
            'betrag'          => $vorabRetourBetrag,
        ]);
    }
}

// Per-Position: kein_lagerabzug für Auftrag-Positionen (schon gebucht) + Retour-Positionen
foreach ($sauberePositionen as &$bp) {
    if ($bp['block'] === 'auftrag') {
        $wa = $webAuftraege[$bp['web_auftrag_id']] ?? null;
        $bp['kein_lagerabzug'] = !empty($bp['kein_lagerabzug'])
            || ($wa && ($wa['im_fach'] || $wa['mitnehmen'] === false));
    } elseif ($bp['block'] === 'retour') {
        $bp['kein_lagerabzug'] = true; // Packplatz hat schon ausgebucht; Rücklagerung erfolgt über menge_geliefert-Tracking
    }
}
unset($bp);

// Ungepackte Auftragsware, die mitgenommen wird, verlässt hier das Lager -> Charge ist bei
// Chargen-Pflicht Pflicht (Klicktest 2026-10-07: wurde ohne Charge gebucht). bon.php fragt
// beim Bezahlen nach; das hier ist die nicht umgehbare Sperre.
$mitnahmeZeilen = array_filter($sauberePositionen, function ($bp) use ($webAuftraege) {
    $wa = $webAuftraege[$bp['web_auftrag_id'] ?? 0] ?? null;
    return $bp['block'] === 'auftrag' && $wa && $wa['mitnehmen'] === true && !$wa['im_fach']
        && !empty($bp['artikel_id']) && $bp['menge'] > 0;
});
if ($mitnahmeZeilen) {
    $cp = Database::getInstance()->prepare("SELECT charge_pflicht FROM artikel WHERE id = ?");
    foreach ($mitnahmeZeilen as $bp) {
        $cp->execute([(int)$bp['artikel_id']]);
        if ((int)$cp->fetchColumn() === 1 && trim((string)($bp['charge'] ?? '')) === '') {
            echo json_encode(['erfolg' => false, 'fehler' => '„' . $bp['bezeichnung'] . '“ ist chargenpflichtig — bitte beim Bezahlen die Charge wählen.']); exit;
        }
    }
}

/**
 * Vorab bezahlte, ungepackte Auftragsware wird mitgenommen: sie steht nicht auf dem Bon
 * (der kassiert nur Unbezahltes), also bucht sie auch KassenService nicht ab -> hier aus dem
 * Lager buchen, mit der gewählten Charge. Liefert je Auftragsposition die abgebuchte Menge.
 */
$bucheVorabBezahlteMitnahme = function (int $aid, string $auftragNr) use ($sauberePositionen, $bonDaten, $benutzerId): array {
    $lagerSvc = new LagerService();
    $kassenSvc = new KassenService();
    $mengen = [];
    foreach ($sauberePositionen as $bp) {
        if ($bp['block'] !== 'auftrag' || (int)($bp['web_auftrag_id'] ?? 0) !== $aid || empty($bp['artikel_id']) || $bp['menge'] <= 0) continue;
        $lagerId = $kassenSvc->lagerFuerArtikel((int)$bp['artikel_id'], (int)($bonDaten['lager_id'] ?? 1));
        if (!empty($bp['charge']) && !empty($bp['nachzutragen_lagerbestand_id'])) {
            $lagerSvc->chargeNachtragen((int)$bp['nachzutragen_lagerbestand_id'], $bp['charge'], (float)$bp['menge'], $benutzerId);
        }
        $lagerSvc->warenausgang([
            'artikel_id'  => (int)$bp['artikel_id'],
            'lager_id'    => $lagerId,
            'menge'       => (float)$bp['menge'],
            'charge'      => $bp['charge'] ?: null,
            'referenz'    => $auftragNr, // Retoure findet die verkaufte Charge über die Auftragsnummer
            'notiz'       => 'Abholung an der Kasse (vorab bezahlt, mitgenommen)',
            'benutzer_id' => $benutzerId,
        ]);
        $pid = (int)$bp['auftrag_position_id'];
        $mengen[$pid] = ($mengen[$pid] ?? 0) + (float)$bp['menge'];
    }
    return $mengen;
};

// ── Abholbestätigung per Mail (nach Abholung ohne Bon bzw. nach Bon) ─────────────
$sendeAbholMail = function (array $auftrag, string $bonNr, array $anhaenge) {
    $kunde = json_decode($auftrag['kunden_snapshot'] ?? '{}', true) ?: [];
    $email = trim($kunde['email'] ?? '');
    if (!$email) return;
    static $firma = null;
    if ($firma === null) {
        $firma = Database::getInstance()->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    $mailer = new Mailer();
    $mailer->sendeTemplate(
        empfaenger:   $email,
        betreff:      'Ihre Bestellung ' . $auftrag['auftrag_nr'] . ' — Vielen Dank für Ihren Einkauf!',
        templatePfad: 'mails/abholung_kasse.html.twig',
        variablen: [
            'logo_base64'    => $mailer->ladeShopLogo((int)($auftrag['shop_id'] ?? 1)),
            'anrede'         => $kunde['anrede']   ?? '',
            'nachname'       => $kunde['nachname'] ?? '',
            'kunde_name'     => trim(($kunde['vorname'] ?? '') . ' ' . ($kunde['nachname'] ?? '')) ?: ($kunde['firma'] ?? ''),
            'auftrag_nummer' => $auftrag['auftrag_nr'],
            'bon_nr'         => $bonNr,
            'firma_email'    => $firma['mail_from_address'] ?? '',
        ],
        anhaenge: $anhaenge,
    );
};

// ── Kein-Bon-Abschluss: alle geladenen Aufträge bereits bezahlt, exakt abgeholt ──
if ($nurAbschliessen && $webAuftraege) {
    $db = Database::getInstance();
    try {
        foreach ($webAuftraege as $wa) {
            if (!$wa['bezahlt']) {
                throw new RuntimeException('Auftrag ' . $wa['auftrag']['auftrag_nr'] . ' ist nicht bezahlt — bitte über "Bezahlen" abschließen.');
            }
        }
        $db->beginTransaction();
        $repo     = new AuftragRepository();
        $lagerId  = (int)($bonDaten['lager_id'] ?? 1);
        $lagerSvc = new LagerService();
        $mails    = [];
        $nummern  = [];

        foreach ($webAuftraege as $aid => $wa) {
            $auftrag = $wa['auftrag'];
            $nummern[] = $auftrag['auftrag_nr'];

            $bonAuftragPos = [];
            foreach ($positionenVon($aid) as $bp) {
                if (!empty($bp['auftrag_position_id'])) {
                    // += : eine Position kann auf mehrere Chargen aufgeteilt sein
                    $bonAuftragPos[(int)$bp['auftrag_position_id']] = ($bonAuftragPos[(int)$bp['auftrag_position_id']] ?? 0) + (float)$bp['menge'];
                }
            }

            $origPosStmt = $db->prepare("SELECT id, menge, menge_geliefert, menge_abgeholt FROM auftrag_positionen WHERE auftrag_id = ?");
            $origPosStmt->execute([$aid]);

            // Bezahlt + nichts zu kassieren: Übergabe aus dem Abholfach. Was nicht mitgeht,
            // bleibt im Fach ("holt er später") — keine Lagerbuchung. "Will er nicht" mit
            // Erstattung läuft immer über einen Bon, nie hier.
            // Ungepackt + "mitgenommen": Ware geht direkt aus dem Regal mit -> abbuchen
            // (vorher wurde hier nichts gebucht und nichts als abgeholt gezählt, Klicktest 2026-10-07)
            $ausRegal = (!$wa['im_fach'] && $wa['mitnehmen'] === true)
                ? $bucheVorabBezahlteMitnahme((int)$aid, $auftrag['auftrag_nr']) : null;

            $alleGeliefert = true;
            foreach ($origPosStmt->fetchAll(PDO::FETCH_ASSOC) as $op) {
                if ($ausRegal !== null) {
                    $imBon = min((float)($ausRegal[$op['id']] ?? 0), (float)$op['menge'] - (float)$op['menge_abgeholt']);
                    $abgeholt = (float)$op['menge_abgeholt'] + $imBon;
                    if ($imBon > 0.001) {
                        $db->prepare("UPDATE auftrag_positionen SET menge_geliefert = menge_geliefert + ?, menge_abgeholt = ? WHERE id = ?")
                           ->execute([$imBon, $abgeholt, $op['id']]);
                    }
                    if ($abgeholt < (float)$op['menge'] - 0.001) $alleGeliefert = false;
                    continue;
                }
                $fach     = $fachVon($op, $wa['status']);
                $imBon    = min((float)($bonAuftragPos[$op['id']] ?? 0), $fach);
                $abgeholt = (float)$op['menge_abgeholt'] + $imBon;
                if ($imBon > 0.001) {
                    $db->prepare("UPDATE auftrag_positionen SET menge_abgeholt = ? WHERE id = ?")->execute([$abgeholt, $op['id']]);
                }
                if ($abgeholt < (float)$op['menge'] - 0.001) $alleGeliefert = false;
            }

            // "abgeschlossen" entscheidet AuftragAbschluss nach der Rechnung (unten)
            $neuerLieferStatus = $alleGeliefert ? 'versendet' : 'teilgeliefert';
            $db->prepare("UPDATE auftraege SET lieferstatus = ?, aktualisiert_am = NOW() WHERE id = ?")->execute([$neuerLieferStatus, $aid]);
            $repo->logStatus($aid,
                ['lieferstatus' => [$auftrag['lieferstatus'], $neuerLieferStatus]],
                'Abgeholt an Kasse — bereits bezahlt — kein Kassenbon'
                    . (count($webAuftraege) > 1 ? ' (Sammelabholung mit ' . count($webAuftraege) . ' Aufträgen)' : ''),
                $benutzerId
            );
            $mails[$aid] = ['auftrag' => $auftrag, 'voll' => $alleGeliefert];
        }
        $db->commit();

        // Vorab bezahlt + abgeholt: an der Kasse fließt kein Geld -> kein Bon. Beleg ist eine
        // Rechnung über die übergebene Ware (Belege-Umbau 2026-10-07), A4 an die Abholmail.
        $dokSvc = new DokumentService();
        foreach ($mails as $aid => $m) {
            $anhang = [];
            try {
                $re = $dokSvc->erstelleRechnung($aid, $benutzerId);
                if ($re['erfolg']) $anhang[] = ['pfad' => $re['pfad'], 'name' => 'Rechnung_' . $re['rechnung_nr'] . '.pdf'];
            } catch (Throwable $eRe) {
                error_log('[AbholungOhneBon Rechnung] ' . $eRe->getMessage());
            }
            AuftragAbschluss::pruefe($aid, $benutzerId);
            // Mails erst nach dem Commit -- ein Mailfehler darf die Abholung nicht zurückrollen
            if ($m['voll']) {
                try { $sendeAbholMail($m['auftrag'], '', $anhang); } catch (Throwable $eMail) { error_log('[AbholungOhneBon Mail] ' . $eMail->getMessage()); }
            }
        }

        echo json_encode(['erfolg' => true, 'auftrag_nr' => implode(', ', $nummern)]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log('[AbholungOhneBon] ' . $e->getMessage());
        echo json_encode(['erfolg' => false, 'fehler' => 'Fehler beim Abschließen: ' . $e->getMessage()]);
    }
    exit;
}

// ── Für Bon-Erstellung: Positionen bereits bezahlter Aufträge stehen nicht auf dem Bon ──
// (nur Extras, Retouren und die Zeilen noch unbezahlter Aufträge werden kassiert).
// Auftrags-Zeilen mit Menge 0 (nicht mitgenommen) gehören auch nicht auf den Beleg --
// die Auftrags-Logik unten sieht sie weiterhin über $sauberePositionen.
$bonErstellungPositionen = array_values(array_filter($sauberePositionen, fn($p) =>
    empty($p['auftrag_position_id']) || abs($p['menge']) > 0.0001
));
$bonErstellungPositionen = array_values(array_filter($bonErstellungPositionen, fn($p) =>
    empty($p['auftrag_position_id']) || !$webAuftraege[$p['web_auftrag_id']]['bezahlt']
));

// Retoure als Gutschein (bon.php::retourAlsGutschein()): die "gutschein_verkauf"-Zeile
// bringt den Bon auf 0. Ihr Betrag = was nach Retouren und Extras tatsächlich zu erstatten
// ist -- serverseitig nachgerechnet, nicht vom Client übernommen. Genau dieser Betrag wird
// unten EIN Gutschein (bis 2026-10-01 wurde je Auftrag der volle Retourbetrag ausgestellt,
// Extra-Käufe im selben Bon wurden dabei nicht abgezogen).
$gutscheinAusgabeBetrag = null;
$gvIndex = null;
foreach ($bonErstellungPositionen as $i => $p) {
    if ($p['block'] === 'gutschein_verkauf') {
        if ($gvIndex !== null) { echo json_encode(['erfolg' => false, 'fehler' => 'Mehrere Gutschein-Ausgaben in einem Bon.']); exit; }
        $gvIndex = $i;
    }
}
if ($gvIndex !== null) {
    $rest = 0.0;
    foreach ($bonErstellungPositionen as $i => $p) {
        if ($i !== $gvIndex) $rest += $p['menge'] * $p['einzelpreis_brutto'] * (1 - $p['rabatt_prozent'] / 100);
    }
    $gutscheinAusgabeBetrag = round(-$rest, 2);
    if ($gutscheinAusgabeBetrag <= 0.005) {
        echo json_encode(['erfolg' => false, 'fehler' => 'Es gibt nichts zu erstatten — Gutschein-Ausgabe nicht möglich.']); exit;
    }
    $bonErstellungPositionen[$gvIndex]['menge']              = 1;
    $bonErstellungPositionen[$gvIndex]['einzelpreis_brutto'] = $gutscheinAusgabeBetrag;
    $bonErstellungPositionen[$gvIndex]['rabatt_prozent']     = 0;
    $bonErstellungPositionen[$gvIndex]['steuer_prozent']     = 0;
}

$bruttoBon = 0.0;
foreach ($bonErstellungPositionen as $p) {
    $bruttoBon += $p['menge'] * $p['einzelpreis_brutto'] * (1 - $p['rabatt_prozent'] / 100);
}
$bonDaten['bruttobetrag'] = round($bruttoBon, 2);

// RKSV-Vorabcheck: eine Netto-Rückgabe (z.B. Retour einer auf anderer Kasse bezahlten
// Bestellung) darf den lokalen Umsatzzähler DIESER Kasse nie negativ machen — jede Kasse
// hat ihren eigenen Zähler. Muss vor jeder Buchung laufen (analog storniereBon()), sonst
// wäre Bargeld schon ausgezahlt und Lager schon korrigiert, bevor der BFR das ablehnt.
if ($bonDaten['bruttobetrag'] < 0) {
    $bfrCheck = new BfrService();
    if ($bfrCheck->wuerdeUmsatzzaehlerNegativWerden($aktuelleKasseId, $bonDaten['bruttobetrag'])) {
        echo json_encode([
            'erfolg' => false,
            'fehler' => 'Rückgabe nicht möglich: Der RKSV-Gesamtumsatzzähler dieser Kasse würde dadurch negativ werden. '
                . 'Bitte administrativ prüfen — ggf. an der Kasse zurücknehmen, an der ursprünglich verkauft wurde.',
        ]);
        exit;
    }
}

// Vorabcheck: keine Position darf mehr zurückgegeben werden, als nach Abzug bereits
// früher über die Kasse retournierter Mengen noch übrig ist — sonst könnte derselbe
// Auftrag bei einem zweiten Kasse-Besuch nochmal (zu viel) zurückgenommen werden. Das
// Client-seitige Stepper-Limit allein ließe sich per direktem POST umgehen.
$retourAnfrageProPosition = [];
foreach ($sauberePositionen as $bp) {
    if ($bp['block'] === 'retour' && !empty($bp['retour_von_position_id'])) {
        $pid = (int)$bp['retour_von_position_id'];
        $retourAnfrageProPosition[$pid] = ($retourAnfrageProPosition[$pid] ?? 0) + abs($bp['menge']);
    }
}
if (!empty($retourAnfrageProPosition)) {
    // Zurückgekommen ODER schon gutgeschrieben (Packplatz-Retoure, ERP-Gutschrift) zählt
    // beides als "nicht mehr offen" -- eine Kassen-Retoure erstattet ja immer auch Geld.
    $chk = Database::getInstance()->prepare("SELECT menge, GREATEST(menge_retourniert, menge_gutgeschrieben) AS erledigt, bezeichnung FROM auftrag_positionen WHERE id = ?");
    foreach ($retourAnfrageProPosition as $pid => $angefragteMenge) {
        $chk->execute([$pid]);
        $origPos = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$origPos) continue;
        $nochOffen = (int)$origPos['menge'] - (int)$origPos['erledigt'];
        if ($angefragteMenge > $nochOffen) {
            echo json_encode([
                'erfolg' => false,
                'fehler' => 'Rückgabe nicht möglich: "' . $origPos['bezeichnung'] . '" hat nur noch ' . $nochOffen
                    . ' Stück offen (Rest bereits früher retourniert) — bitte Menge korrigieren.',
            ]);
            exit;
        }
    }
}

// ── Bezahlen mit Gutschein: vor dem Signieren prüfen, Beträge serverseitig festlegen ──
// Eingelöst wird erst NACH erfolgreich erstelltem Bon (unten) -- sonst wäre das
// Guthaben weg, falls der BFR den Bon ablehnt. Reicht das Guthaben nicht, zahlt der
// Kunde den Rest bar oder mit Karte (rest_zahlungsart); bar_betrag ist dabei der
// NETTO-Rest (ohne Rückgeld), damit der Kassenstand stimmt.
$gutscheinZahlung = null;
if ($bonDaten['zahlungsart'] === 'gutschein') {
    require_once __DIR__ . '/../../src/modules/gutscheine/GutscheinService.php';
    if ($gutscheinKaufPositionen) {
        echo json_encode(['erfolg' => false, 'fehler' => 'Gutscheine können nicht mit einem Gutschein bezahlt werden.']); exit;
    }
    if ($bonDaten['bruttobetrag'] <= 0) {
        echo json_encode(['erfolg' => false, 'fehler' => 'Gutschein-Zahlung ist nur bei einem positiven Bon-Betrag möglich.']); exit;
    }
    $gsPruefung = (new GutscheinService())->pruefeEinloesbar((string)($bonDaten['gutschein_code'] ?? ''));
    if (!$gsPruefung['erfolg']) {
        echo json_encode(['erfolg' => false, 'fehler' => implode(' ', $gsPruefung['fehler'])]); exit;
    }
    $gs       = $gsPruefung['gutschein'];
    $gsBetrag = round(min((float)$gs['restguthaben'], $bonDaten['bruttobetrag']), 2);
    $offen    = round($bonDaten['bruttobetrag'] - $gsBetrag, 2);
    $restArt  = $input['rest_zahlungsart'] ?? null;

    $bonDaten['gutschein_code']   = $gs['code'];
    $bonDaten['gutschein_betrag'] = $gsBetrag;
    $bonDaten['bar_betrag']       = null;
    $bonDaten['karten_betrag']    = null;
    $bonDaten['gegeben']          = null;
    $bonDaten['rueckgeld']        = null;
    if ($offen > 0.005) {
        if ($restArt === 'bar') {
            $bonDaten['bar_betrag'] = $offen;
            $gegeben = isset($input['gegeben']) ? (float)$input['gegeben'] : 0.0;
            if ($gegeben >= $offen) {
                $bonDaten['gegeben']   = $gegeben;
                $bonDaten['rueckgeld'] = round($gegeben - $offen, 2);
            }
        } elseif ($restArt === 'karte_extern') {
            $bonDaten['karten_betrag'] = $offen;
        } else {
            echo json_encode(['erfolg' => false, 'fehler' => 'Guthaben reicht nicht — Restbetrag € '
                . number_format($offen, 2, ',', '.') . ' bitte bar oder mit Karte kassieren.']); exit;
        }
    }
    $gutscheinZahlung = ['code' => $gs['code'], 'id' => (int)$gs['id'], 'betrag' => $gsBetrag];
}

if ($zahlungPositionen && $webAuftraege) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Rechnung bezahlen bitte als eigenen Bon — nicht zusammen mit einer Abholung oder Retoure.']); exit;
}

$result = $service->erstelleBon($bonDaten, $bonErstellungPositionen, $benutzerId);

// ── Zahlbeleg: Zahlung am Auftrag buchen (erscheint in der Zahlungsinfo der Rechnung) ──
if ($result['erfolg'] && $zahlungPositionen) {
    require_once __DIR__ . '/../../src/modules/auftraege/AuftragService.php';
    $wegZ = ['bar' => 'bar', 'karte_extern' => 'karte', 'gutschein' => 'gutschein'][$bonDaten['zahlungsart']] ?? 'sonstig';
    foreach ($zahlungPositionen as $zp) {
        try {
            $zr = (new AuftragService())->bucheZahlung($zp['auftrag_id'], $zp['betrag'], date('Y-m-d'),
                'Zahlbeleg ' . $result['bon_nr'], $wegZ, (int)$result['bon_id']);
            if (empty($zr['erfolg'])) throw new RuntimeException($zr['fehler'] ?? 'unbekannt');
        } catch (Throwable $ex) {
            Logger::log('kasse.zahlbeleg_fehler', 'kassen_bons', (int)$result['bon_id'], [
                'auftrag_id' => $zp['auftrag_id'], 'fehler' => $ex->getMessage(),
            ], $benutzerId, 'error');
            $result['warnungen'][] = 'Zahlbeleg erstellt, aber die Zahlung zu ' . $zp['auftrag_nr']
                . ' konnte nicht gebucht werden (' . $ex->getMessage() . ') — bitte im Auftrag nachbuchen.';
        }
    }
}

// ── Nach erfolgreichem Bon: Gutschein einlösen bzw. verkaufte Gutscheine ausstellen ──
// Läuft VOR dem echo, damit die Kasse Codes/PDF-Links direkt in der Antwort bekommt.
// Ein Fehler hier darf den (bereits signierten) Bon nicht mehr kippen -> Warnung + Log.
if ($result['erfolg'] && !empty($result['bon_id']) && ($gutscheinZahlung || $gutscheinKaufPositionen)) {
    require_once __DIR__ . '/../../src/modules/gutscheine/GutscheinService.php';
    $gsService = new GutscheinService();
    $bonIdGs   = (int)$result['bon_id'];
    $result['warnungen'] = [];

    if ($gutscheinZahlung) {
        try {
            $e = $gsService->einloesen($gutscheinZahlung['code'], $gutscheinZahlung['betrag'], 'kasse', null, $bonIdGs, $benutzerId);
            Database::getInstance()->prepare("UPDATE kassen_bons SET gutschein_id = ? WHERE id = ?")
                ->execute([$gutscheinZahlung['id'], $bonIdGs]);
            if (!$e['erfolg']) {
                throw new RuntimeException(implode(' ', $e['fehler'] ?? []));
            }
            if (!empty($e['neuer_code'])) {
                $neu = (new GutscheinRepository())->findByCode($e['neuer_code']);
                $result['gutschein_rest'] = ['id' => (int)$neu['id'], 'code' => $neu['code'], 'betrag' => (float)$neu['betrag']];
            }
        } catch (Throwable $ex) {
            Logger::log('gutschein.kasse_einloesung_fehler', 'kassen_bons', $bonIdGs, [
                'code' => $gutscheinZahlung['code'], 'fehler' => $ex->getMessage(), 'bon_nr' => $result['bon_nr'] ?? '',
            ], $benutzerId, 'error');
            $result['warnungen'][] = 'Bon wurde erstellt, aber der Gutschein konnte nicht abgebucht werden ('
                . $ex->getMessage() . ') — bitte in der Gutschein-Verwaltung prüfen.';
        }
    }

    $result['gutscheine_ausgestellt'] = [];
    foreach ($gutscheinKaufPositionen as $gp) {
        for ($i = 0; $i < (int)$gp['menge']; $i++) {
            try {
                $g = $gsService->erstelleGutschein([
                    'betrag'          => $gp['einzelpreis_brutto'],
                    'kunden_id'       => $bonDaten['kunden_id'],
                    'empfaenger_name' => $gp['gutschein_empfaenger'],
                    'kanal_erstellt'  => 'kasse',
                    'kassen_bon_id'   => $bonIdGs,
                    'versandart'      => 'selbst_ausdrucken',
                ], $benutzerId);
                if (!$g['erfolg']) throw new RuntimeException(implode(' ', $g['fehler'] ?? []));
                $result['gutscheine_ausgestellt'][] = ['id' => $g['id'], 'code' => $g['code'], 'betrag' => $gp['einzelpreis_brutto']];
            } catch (Throwable $ex) {
                Logger::log('gutschein.kasse_verkauf_fehler', 'kassen_bons', $bonIdGs, [
                    'betrag' => $gp['einzelpreis_brutto'], 'fehler' => $ex->getMessage(), 'bon_nr' => $result['bon_nr'] ?? '',
                ], $benutzerId, 'error');
                $result['warnungen'][] = 'Gutschein über € ' . number_format($gp['einzelpreis_brutto'], 2, ',', '.')
                    . ' konnte nicht erstellt werden — bitte in der Gutschein-Verwaltung manuell nachtragen.';
            }
        }
    }
}

echo json_encode($result);

// ─── Packplatz-Rücklagerung: Freitext-Retour (kein Auftrag) ─────────────────────
// Auftrag-gebundene Retouren werden weiter unten je Auftrag behandelt. Kein_lagerabzug
// gilt für JEDE block='retour'-Position (siehe oben) — physisch liegt die Ware jetzt am
// Tresen und muss von Packplatz eingelagert werden, siehe packplatz/ruecklagerungen.php.
// Retour-Zeilen mit web_auftrag_id (weniger mitgenommen als abholbereit) bucht die
// Auftrags-Logik unten direkt zurück, die Ware hat das Haus nie verlassen.
if ($result['erfolg']) {
    $ruecklagerungRepo = new RuecklagerungRepository();
    foreach ($sauberePositionen as $bp) {
        if ($bp['block'] === 'retour' && empty($bp['retour_von_position_id']) && empty($bp['web_auftrag_id']) && !empty($bp['artikel_id'])) {
            $ruecklagerungRepo->insert([
                'kassen_bon_id' => $result['bon_id'],
                'bon_nr'        => $result['bon_nr'],
                'artikel_id'    => $bp['artikel_id'],
                'bezeichnung'   => $bp['bezeichnung'],
                'menge'         => (int)round(abs($bp['menge'])),
                'charge'        => $bp['charge'],
                'kasse_id'      => $aktuelleKasseId,
            ]);
        }
    }
}

// ─── Web-Aufträge abschließen ──────────────────────────────────────────────────
if ($result['erfolg'] && $webAuftraege) {
    $db    = Database::getInstance();
    $bonId = $result['bon_id'] ?? null;
    $bonNr = $result['bon_nr'] ?? '';

    // ── K1 Kassen-Auftrag aufteilen ──────────────────────────────────────────
    // erstelleBon() erstellt immer einen K1-Auftrag mit ALLEN Bon-Positionen.
    // Strategie:
    //   Keine Extras → K1 löschen, Bon direkt auf den (ersten) Web-Auftrag zeigen
    //   Extras vorhanden → K1 behält nur die Extra-Positionen (separater Auftrag),
    //                      Web-Aufträge und K1 sind über den Bon verknüpft.
    try {
        $k1AuftragId = null;
        if ($bonId) {
            $k1Row = $db->prepare("SELECT auftrag_id FROM kassen_bons WHERE id = ?");
            $k1Row->execute([$bonId]);
            $k1AuftragId = (int)$k1Row->fetchColumn() ?: null;
        }

        // Extra-Positionen = Bon-Artikel ohne auftrag_position_id (inkl. Divers-Artikel
        // ohne artikel_id — die bekommen beim Einfügen unten den Platzhalter 99-9999,
        // genau wie erstelleBon() das bei der ursprünglichen K1-Erstellung schon macht;
        // vorher fielen sie hier komplett raus, siehe project_kasse_bon_design Memory)
        $extraPositionen = array_values(array_filter($sauberePositionen, fn($bp) =>
            empty($bp['auftrag_position_id']) && $bp['block'] !== 'zahlung' // Zahlung/Rückzahlung ist kein Verkauf
        ));

        if ($k1AuftragId && !isset($webAuftraege[$k1AuftragId])) {
            if (empty($extraPositionen)) {
                // Keine Extras → K1 vollständig entfernen
                $db->prepare("UPDATE kassen_bons SET auftrag_id = ? WHERE id = ?")
                   ->execute([$webAuftragId, $bonId]);
                $db->prepare("DELETE FROM auftrag_positionen WHERE auftrag_id = ?")
                   ->execute([$k1AuftragId]);
                $db->prepare("DELETE FROM auftraege WHERE id = ?")
                   ->execute([$k1AuftragId]);
                $k1AuftragId = null;
            } else {
                // Extras vorhanden → K1 auf Extra-Positionen reduzieren
                // Alle alten K1-Positionen löschen und nur Extras neu einfügen
                $db->prepare("DELETE FROM auftrag_positionen WHERE auftrag_id = ?")
                   ->execute([$k1AuftragId]);

                $diversArtikelId = $service->getDiversArtikelId();
                $extraNetto  = 0.0;
                $extraSteuer = 0.0;
                $extraBrutto = 0.0;
                foreach ($extraPositionen as $sortIdx => $ep) {
                    $artIdPos = !empty($ep['artikel_id']) ? (int)$ep['artikel_id'] : $diversArtikelId;
                    if (!$artIdPos) continue; // 99-9999 nicht angelegt? überspringen (wie erstelleBon())
                    $rab      = 1 - $ep['rabatt_prozent'] / 100;
                    $nettEP   = round($ep['einzelpreis_brutto'] / (1 + $ep['steuer_prozent'] / 100), 4);
                    $gesNetto = round($nettEP * $ep['menge'] * $rab, 4);
                    $gesBrut  = $ep['menge'] * $ep['einzelpreis_brutto'] * $rab;
                    $db->prepare("
                        INSERT INTO auftrag_positionen
                            (auftrag_id, artikel_id, bezeichnung, ean, menge, menge_geliefert,
                             einzelpreis_netto, steuer_prozent, rabatt_prozent, gesamtpreis_netto, sort_order)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ")->execute([
                        $k1AuftragId, $artIdPos, $ep['bezeichnung'], $ep['ean'],
                        $ep['menge'], $ep['menge'],
                        $nettEP, $ep['steuer_prozent'], $ep['rabatt_prozent'], $gesNetto, $sortIdx,
                    ]);
                    $steuerAnteil = $gesBrut - $gesBrut / (1 + $ep['steuer_prozent'] / 100);
                    $extraNetto  += $gesBrut / (1 + $ep['steuer_prozent'] / 100);
                    $extraSteuer += $steuerAnteil;
                    $extraBrutto += $gesBrut;
                }

                // K1-Beträge auf Extra-Summe korrigieren + Kunde vom (ersten) Web-Auftrag übernehmen
                $hauptAuftrag = $webAuftraege[$webAuftragId]['auftrag'];
                $db->prepare("
                    UPDATE auftraege SET
                        nettobetrag = ?, steuerbetrag = ?, bruttobetrag = ?,
                        kassen_bon_id = ?, kunden_id = ?, kunden_snapshot = ?,
                        aktualisiert_am = NOW()
                    WHERE id = ?
                ")->execute([
                    round($extraNetto, 2), round($extraSteuer, 2), round($extraBrutto, 2),
                    $bonId,
                    $hauptAuftrag['kunden_id'] ?: null,
                    $hauptAuftrag['kunden_snapshot'],  // immer kopieren — enthält den Namen für die Liste
                    $k1AuftragId,
                ]);
            }
        }

        // Bon → Aufträge: web_auftrag_id = erster Auftrag (Altbestand/bestehende Abfragen),
        // vollständige Liste in kassen_bon_auftraege
        if ($bonId) {
            $db->prepare("UPDATE kassen_bons SET web_auftrag_id = ? WHERE id = ?")
               ->execute([$webAuftragId, $bonId]);
            $stmtKba = $db->prepare("INSERT IGNORE INTO kassen_bon_auftraege (bon_id, auftrag_id, vorher_bezahlt) VALUES (?, ?, ?)");
            foreach ($webAuftraege as $aid => $wa) {
                $stmtKba->execute([$bonId, $aid, $wa['bezahlt'] ? 1 : 0]);
            }
        }
    } catch (Throwable $e) {
        error_log('[AbholungKasse K1] ' . $e->getMessage());
    }

    // Bon als A4-PDF für die Abholmails -- einmal erzeugen, für alle Aufträge verwenden
    $bonAnhang = null;
    $holeBonAnhang = function () use (&$bonAnhang, $bonId, $bonNr): array {
        if ($bonAnhang !== null) return $bonAnhang;
        $bonAnhang = [];
        if (!$bonId) return $bonAnhang;
        try {
            require_once __DIR__ . '/../../vendor/autoload.php';
            require_once __DIR__ . '/../../src/modules/kasse/BonA4Renderer.php';

            $htmlA4 = BonA4Renderer::render((int)$bonId, fuerPdf: true);
            if ($htmlA4 !== null) {
                $opt = new \Dompdf\Options();
                $opt->set('defaultFont', 'DejaVu Sans');
                $opt->set('isRemoteEnabled', false);
                $dom = new \Dompdf\Dompdf($opt);
                $dom->loadHtml($htmlA4, 'UTF-8');
                $dom->setPaper('A4', 'portrait');
                $dom->render();

                $bonDir = __DIR__ . '/../../storage/bons/';
                if (!is_dir($bonDir)) mkdir($bonDir, 0755, true);
                $bonPdfPfad = $bonDir . $bonId . '.pdf';
                file_put_contents($bonPdfPfad, $dom->output());
                $bonAnhang = [['pfad' => $bonPdfPfad, 'name' => 'Kassenbon_' . $bonNr . '.pdf']];
            }
        } catch (Throwable $ePdf) {
            error_log('[BonPDF] ' . $ePdf->getMessage());
        }
        return $bonAnhang;
    };

    $repo      = new AuftragRepository();
    // Zahlungsweg für auftrag_zahlungen (Zahlungsinfo auf der Rechnung)
    $wegBon    = ['bar' => 'bar', 'karte_extern' => 'karte', 'gutschein' => 'gutschein'][$bonDaten['zahlungsart']] ?? 'sonstig';
    $lagerId   = (int)($bonDaten['lager_id'] ?? 1);
    $lagerSvc  = new LagerService();
    $anzahlAuf = count($webAuftraege);

    // Retoure als Gutschein (bon.php::retourAlsGutschein()) -- erkennbar an der zusätzlichen
    // "gutschein_verkauf"-Bon-Position, die den Bon auf Summe 0 bringt (Retour negativ +
    // Gutschein-Verkauf positiv) und dadurch RKSV-sauber signiert wird, statt eine stille
    // DB-Zeile ohne Bon-Bezug zu sein (Jacky-Anfrage 2026-08-29, siehe project_gutscheine.md).
    $istGutscheinAusgabe = $gutscheinAusgabeBetrag !== null;
    $ausgabeGutschein    = null;
    if ($istGutscheinAusgabe) {
        $erstattAuftrag = null;
        foreach ($webAuftraege as $aid => $wa) {
            foreach ($positionenVon($aid) as $bp) {
                if ($bp['block'] === 'retour' || $bp['block'] === 'zahlung') { $erstattAuftrag = $wa['auftrag']; break 2; }
            }
        }
        require_once __DIR__ . '/../../src/modules/gutscheine/GutscheinService.php';
        $gErgebnis = (new GutscheinService())->erstelleGutschein([
            'betrag'              => $gutscheinAusgabeBetrag,
            'kunden_id'           => ($erstattAuftrag ?? $webAuftraege[$webAuftragId]['auftrag'])['kunden_id'] ?? null,
            'kanal_erstellt'      => 'kasse',
            'auftrag_id_ursprung' => (int)($erstattAuftrag ?? $webAuftraege[$webAuftragId]['auftrag'])['id'],
            'kassen_bon_id'       => $bonId,
            'versandart'          => 'selbst_ausdrucken',
        ], $benutzerId);
        if ($gErgebnis['erfolg']) {
            $ausgabeGutschein = $gErgebnis;
        } else {
            Logger::log('gutschein.kasse_ausgabe_fehler', 'kassen_bons', $bonId, [
                'fehler' => $gErgebnis['fehler'] ?? [], 'bon_nr' => $bonNr, 'betrag' => $gutscheinAusgabeBetrag,
            ], $benutzerId, 'error');
        }
    }

    foreach ($webAuftraege as $aid => $wa) {
        try {
            $auftrag          = $wa['auftrag'];
            $auftragStatus    = $wa['status'];
            $mitnehmen        = $wa['mitnehmen'];
            $warBezahlt       = $wa['bezahlt'];
            $eigenePositionen = $positionenVon($aid);

            // Bon-Positionen mit auftrag_position_id (= aus dem Auftrag geladen)
            $bonAuftragPos = [];
            foreach ($eigenePositionen as $bp) {
                if (!empty($bp['auftrag_position_id'])) {
                    // += : eine Position kann auf mehrere Chargen aufgeteilt sein
                    $bonAuftragPos[(int)$bp['auftrag_position_id']] = ($bonAuftragPos[(int)$bp['auftrag_position_id']] ?? 0) + $bp['menge'];
                }
            }

            // ── Packplatz-Rücklagerung: Auftrag-gebundene Retoure ────────────
            // Nur wenn NICHT abholbereit — der Fall "Kunde nimmt beim Abholen weniger
            // mit" bucht weiter unten schon automatisch zurück ($rueck-Logik), weil die
            // Ware nie das Haus verlassen hat. Eine physische Retoure einer bereits
            // versendeten/abgeschlossenen Bestellung braucht dagegen echte manuelle
            // Sichtprüfung + Einlagerung durch Packplatz.
            $istFach = $wa['im_fach'];
            if (!$istFach) {
                $ruecklagerungRepo = new RuecklagerungRepository();
                foreach ($eigenePositionen as $bp) {
                    if ($bp['block'] === 'retour' && !empty($bp['retour_von_position_id']) && !empty($bp['artikel_id'])) {
                        $ruecklagerungRepo->insert([
                            'kassen_bon_id' => $bonId,
                            'bon_nr'        => $bonNr,
                            'auftrag_id'    => $aid,
                            'auftrag_nr'    => $auftrag['auftrag_nr'],
                            'artikel_id'    => $bp['artikel_id'],
                            'bezeichnung'   => $bp['bezeichnung'],
                            'menge'         => (int)round(abs($bp['menge'])),
                            'charge'        => $bp['charge'],
                            'kasse_id'      => $aktuelleKasseId,
                        ]);
                    }
                }
            }

            // Retour-Positionen (block='retour') je ursprünglicher Position summieren —
            // für menge_retourniert, damit gutschrift_erstellen.php nicht nochmal dieselbe
            // Menge gutschreiben kann, die hier schon über die Kasse erstattet wurde.
            $retourProPosition = [];
            foreach ($eigenePositionen as $bp) {
                if ($bp['block'] === 'retour' && !empty($bp['retour_von_position_id'])) {
                    $pid = (int)$bp['retour_von_position_id'];
                    $retourProPosition[$pid] = ($retourProPosition[$pid] ?? 0) + abs($bp['menge']);
                }
            }

            // Alle Original-Positionen des Auftrags laden (inkl. artikel_id + charge für Rückbuchung)
            $origPosStmt = $db->prepare("SELECT * FROM auftrag_positionen WHERE auftrag_id = ?");
            $origPosStmt->execute([$aid]);
            $origPositionen = $origPosStmt->fetchAll(PDO::FETCH_ASSOC);

            // Vorab bezahlt + ungepackt mitgenommen: steht nicht auf dem Bon -> hier abbuchen
            if ($warBezahlt && !$istFach && $mitnehmen === true) {
                $bucheVorabBezahlteMitnahme((int)$aid, $auftrag['auftrag_nr']);
            }

            // menge_geliefert aktualisieren + prüfen ob alle geliefert
            $alleGeliefert = true;
            $verzichtWert  = 0.0; // Bruttowert des Rests, den der Kunde nicht will
            foreach ($origPositionen as $op) {
                $imBon   = (float)($bonAuftragPos[$op['id']] ?? 0);
                $gepackt = (float)$op['menge'];

                // Abholfach: übergeben werden kann nur, was gepackt im Fach liegt
                $fach = $fachVon($op, $auftragStatus);
                if ($istFach) $imBon = min($imBon, $fach);

                // "Will er nicht": höchstens die Menge, die nach dieser Abholung noch offen wäre
                $nochOffen = $istFach
                    ? $fach - $imBon
                    : $gepackt - (float)($op['menge_geliefert'] ?? 0) - $imBon;
                $verzicht = min((float)($restVerzicht[$aid][(int)$op['id']] ?? 0), max(0.0, $nochOffen));
                if ($verzicht > 0.001) {
                    $db->prepare("UPDATE auftrag_positionen SET menge_retourniert = menge_retourniert + ?, menge_gutgeschrieben = menge_gutgeschrieben + ? WHERE id = ?")
                       ->execute([$verzicht, $warBezahlt ? $verzicht : 0, $op['id']]);
                    $verzichtWert += Positionsrechnung::ausPosition($op, $verzicht)['brutto'];
                }

                if (!empty($retourProPosition[$op['id']])) {
                    // Kassen-Retoure = Ware zurück UND erstattet (bar oder Gutschein)
                    $db->prepare("UPDATE auftrag_positionen SET menge_retourniert = menge_retourniert + ?, menge_gutgeschrieben = menge_gutgeschrieben + ? WHERE id = ?")
                       ->execute([$retourProPosition[$op['id']], $retourProPosition[$op['id']], $op['id']]);
                }

                // Unbezahlte Auftragsware, die hier kassiert wird: der Bon ist ihr Beleg —
                // eine spätere Rechnung (z.B. Packplatz bei "nur Zahlung, Versand folgt")
                // darf sie nicht nochmal verrechnen.
                if (!$warBezahlt && $imBon > 0.001) {
                    $db->prepare("UPDATE auftrag_positionen SET menge_verrechnet = menge_verrechnet + ? WHERE id = ?")
                       ->execute([(int)round($imBon), $op['id']]);
                }

                $abgeholt = (float)$op['menge_abgeholt'] + $imBon + $verzicht;
                if ($istFach) {
                    // Aus dem Abholfach: Übergabe ohne Lagerbuchung (Packplatz hat schon ausgebucht).
                    // "Holt er später" bleibt im Fach. "Will er nicht" → Rücklagerung am Packplatz
                    // (Kontrolle + Einlagerung dort, Bestand erst dann).
                    if ($verzicht > 0.001 && !empty($op['artikel_id'])) {
                        (new RuecklagerungRepository())->insert([
                            'kassen_bon_id'       => $bonId,
                            'bon_nr'              => $bonNr,
                            'auftrag_id'          => $aid,
                            'auftrag_nr'          => $auftrag['auftrag_nr'],
                            'auftrag_position_id' => (int)$op['id'],
                            'artikel_id'          => (int)$op['artikel_id'],
                            'bezeichnung'         => $op['bezeichnung'],
                            'menge'               => (int)round($verzicht),
                            'charge'              => $op['charge'] ?? null,
                            'kasse_id'            => $aktuelleKasseId,
                        ]);
                    }
                    $db->prepare("UPDATE auftrag_positionen SET menge_abgeholt = ? WHERE id = ?")
                       ->execute([$abgeholt, $op['id']]);
                    if ($abgeholt < $gepackt - 0.001) $alleGeliefert = false;
                } else {
                    // Nicht gepackt ("mitnehmen"): Ware geht direkt aus dem Regal mit (Bon bucht ab)
                    $neu = (float)($op['menge_geliefert'] ?? 0) + $imBon;
                    $db->prepare("UPDATE auftrag_positionen SET menge_geliefert = ?, menge_abgeholt = ? WHERE id = ?")
                       ->execute([$neu, $abgeholt, $op['id']]);
                    if ($neu + $verzicht < $gepackt - 0.001) $alleGeliefert = false;
                }
            }

            // ── Bezahlten Betrag (nur Anteil dieses Auftrags) berechnen ──────
            // Ohne eigene Auftrags-Zeilen im Bon (z.B. reine Retoure) gilt wie bisher der
            // Auftragsbetrag; sind Zeilen da, zählt nur, was tatsächlich mitgeht.
            $auftragAnteil = 0.0;
            foreach ($eigenePositionen as $bp) {
                if (!empty($bp['auftrag_position_id'])) {
                    $rab = 1 - ($bp['rabatt_prozent'] / 100);
                    $auftragAnteil += $bp['menge'] * $bp['einzelpreis_brutto'] * $rab;
                }
            }
            $auftragAnteil = round($auftragAnteil, 2);
            if (!$bonAuftragPos) {
                $auftragAnteil = (float)$auftrag['bruttobetrag'];
            }

            // ── Zahlung buchen ────────────────────────────────────────────────
            // $summeBezahltGesamt bleibt null wenn $warBezahlt (Retour-Zweig, siehe unten) —
            // dort wird der Zahlstatus über $retourBetrag entschieden, nicht über die Summe.
            $summeBezahltGesamt = null;
            $retourBetrag       = 0.0;
            if (!$warBezahlt) {
                if ($auftragAnteil > 0.005) {
                    $db->prepare("
                        INSERT INTO auftrag_zahlungen (auftrag_id, betrag, buchungsdatum, notiz, erfasst_von, kassen_bon_id, zahlungsweg)
                        VALUES (?, ?, CURDATE(), ?, ?, ?, ?)
                    ")->execute([$aid, $auftragAnteil, 'Bezahlt an der Kasse — Bon ' . $bonNr, $benutzerId, $bonId, $wegBon]);
                }

                // Kumulierte Summe ALLER Zahlungen (nicht nur dieser Transaktion!) entscheidet
                // über "vollständig bezahlt" — ein $auftragAnteil-Vergleich allein würde bei
                // mehreren Teilzahlungen oder Rundungsdifferenzen zwischen Positions- und
                // Kopfbetrag fälschlich dauerhaft auf 'teilbezahlt' stehen bleiben.
                $summeStmt = $db->prepare("SELECT COALESCE(SUM(betrag), 0) FROM auftrag_zahlungen WHERE auftrag_id = ?");
                $summeStmt->execute([$aid]);
                $summeBezahltGesamt = (float)$summeStmt->fetchColumn();
            } else {
                // Bereits bezahlt: nur Erstattung (negativen Betrag) buchen wenn Retour.
                // Direkt aus den block='retour'-Positionen dieses Auftrags berechnen — NICHT
                // über bruttobetrag-auftragAnteil (Retour-Zeilen tragen bewusst keine
                // auftrag_position_id).
                foreach ($eigenePositionen as $bp) {
                    // auch Rückzahlungen (block zahlung, negativ) -- siehe Umstellung oben
                    if (($bp['block'] ?? null) === 'retour' || (($bp['block'] ?? null) === 'zahlung' && $bp['menge'] * $bp['einzelpreis_brutto'] < 0)) {
                        $rab = 1 - ($bp['rabatt_prozent'] / 100);
                        $retourBetrag += abs($bp['menge']) * $bp['einzelpreis_brutto'] * $rab;
                    }
                }
                $retourBetrag = round($retourBetrag, 2);

                if ($retourBetrag > 0.005 && $istGutscheinAusgabe) {
                    // Auch bei Gutschein-Erstattung MUSS ein negativer auftrag_zahlungen-Posten
                    // gebucht werden -- $offenBetrag in detail.php rechnet sonst mit dem vollen
                    // Ursprungsbetrag weiter und zeigt fälschlich "Überbezahlt/Gutschrift" statt
                    // "Vollständig bezahlt". Erstattet wird der Retourwert des Auftrags; ein Teil
                    // davon kann im selben Bon in Extra-Ware geflossen sein, der Rest steckt im
                    // (einen) Gutschein des Bons.
                    $db->prepare("
                        INSERT INTO auftrag_zahlungen (auftrag_id, betrag, buchungsdatum, notiz, erfasst_von, kassen_bon_id, zahlungsweg)
                        VALUES (?, ?, CURDATE(), ?, ?, ?, 'gutschein')
                    ")->execute([
                        $aid, -$retourBetrag,
                        'Rückerstattung an der Kasse — Gutschein ' . ($ausgabeGutschein['code'] ?? '(Fehler, siehe Log)') . ' — Bon ' . $bonNr,
                        $benutzerId, $bonId,
                    ]);
                } elseif ($retourBetrag > 0.005) {
                    $db->prepare("
                        INSERT INTO auftrag_zahlungen (auftrag_id, betrag, buchungsdatum, notiz, erfasst_von, kassen_bon_id, zahlungsweg)
                        VALUES (?, ?, CURDATE(), ?, ?, ?, ?)
                    ")->execute([$aid, -$retourBetrag, 'Rückerstattung bar an der Kasse — Bon ' . $bonNr, $benutzerId, $bonId, $wegBon]);
                }
            }

            // ── Status setzen ─────────────────────────────────────────────────
            $sammelHinweis = $anzahlAuf > 1 ? ' (Sammelabholung)' : '';
            if ($warBezahlt) {
                // "erstattet" nur, wenn nach der Rückzahlung nichts mehr bezahlt bleibt — eine
                // Teil-Erstattung (Kunde behält einen Teil der Ware) bleibt "bezahlt"
                $nettoStmt = $db->prepare("SELECT COALESCE(SUM(betrag), 0) FROM auftrag_zahlungen WHERE auftrag_id = ?");
                $nettoStmt->execute([$aid]);
                $neuerZahlStatus = ($retourBetrag > 0.005 && (float)$nettoStmt->fetchColumn() <= 0.005) ? 'erstattet' : 'bezahlt';
            } else {
                // Was der Kunde nicht will, muss er auch nicht zahlen
                $neuerZahlStatus = $summeBezahltGesamt >= (float)$auftrag['bruttobetrag'] - $verzichtWert - 0.01 ? 'bezahlt'
                                 : ($summeBezahltGesamt > 0.005 ? 'teilbezahlt' : $auftrag['zahlungsstatus']);
            }
            if ($istFach || $mitnehmen === true) {
                // "abgeschlossen" entscheidet AuftragAbschluss (unten, nach Rechnung/Bon)
                $neuerLieferStatus = $alleGeliefert ? 'versendet' : 'teilgeliefert';

                $db->prepare("
                    UPDATE auftraege
                    SET zahlungsstatus = ?, lieferstatus = ?, aktualisiert_am = NOW()
                    WHERE id = ?
                ")->execute([$neuerZahlStatus, $neuerLieferStatus, $aid]);

                $repo->logStatus($aid,
                    ['zahlungsstatus' => [$auftrag['zahlungsstatus'], $neuerZahlStatus],
                     'lieferstatus'   => [$auftrag['lieferstatus'],   $neuerLieferStatus]],
                    ($mitnehmen === true ? 'Mitgenommen' : 'Abgeholt') . ' und bezahlt an der Kasse — Bon ' . $bonNr . $sammelHinweis,
                    $benutzerId
                );
            } else {
                // nur Zahlung — Lieferstatus unverändert. War der Auftrag schon bezahlt
                // (versendet/teilgeliefert/abgeschlossen-Retoure, siehe Redesign 2026-07-08),
                // entscheidet der echte Retourbetrag statt der Kumulierten-Summe-Formel —
                // die gilt nur für den "wird hier erstmals bezahlt"-Fall.
                $db->prepare("
                    UPDATE auftraege SET zahlungsstatus = ?, aktualisiert_am = NOW() WHERE id = ?
                ")->execute([$neuerZahlStatus, $aid]);

                $repo->logStatus($aid,
                    ['zahlungsstatus' => [$auftrag['zahlungsstatus'], $neuerZahlStatus]],
                    ($warBezahlt ? 'Retoure an der Kasse' : 'Nur Zahlung an der Kasse — Versand/Abholung folgt') . ' — Bon ' . $bonNr . $sammelHinweis,
                    $benutzerId
                );
            }

            // ── kassen_bon_id auf Auftrag setzen (sperrt Rechnung-Erstellung) ─
            // NUR wenn der Auftrag durch DIESE Transaktion überhaupt erst bezahlt/fakturiert
            // wird ($warBezahlt war beim Laden false) — war er schon vorher bezahlt
            // (z.B. eigene Rechnung, PayPal), darf ein späterer Retoure/Extra-Bon diese nicht
            // verdrängen.
            if ($bonId && !$warBezahlt) {
                $db->prepare("UPDATE auftraege SET kassen_bon_id = ? WHERE id = ?")
                   ->execute([$bonId, $aid]);
            }

            // ── Beleg für vorab bezahlte Auftragsware ────────────────────────
            // Steht nicht auf dem Bon (der kassiert nur Extras/Retouren) -> Rechnung über
            // die jetzt übergebene Ware. Unbezahlte Ware steht auf dem Bon (verrechnet, s.o.),
            // dann findet die Rechnung nichts und es entsteht keine.
            $rechnungAnhang = [];
            if ($warBezahlt && ($istFach || $mitnehmen === true)) {
                try {
                    $re = (new DokumentService())->erstelleRechnung($aid, $benutzerId);
                    if ($re['erfolg']) $rechnungAnhang[] = ['pfad' => $re['pfad'], 'name' => 'Rechnung_' . $re['rechnung_nr'] . '.pdf'];
                } catch (Throwable $eRe) {
                    error_log('[AbholungKasse Rechnung] ' . $eRe->getMessage());
                }
            }
            AuftragAbschluss::pruefe($aid, $benutzerId);

            // ── Abholbestätigungs-Mail (nur bei vollständiger Übergabe) ──────
            // Anhang: Bon als A4 (wenn hier kassiert wurde) und/oder die Rechnung
            if ($alleGeliefert) {
                $sendeAbholMail($auftrag, $bonNr, array_merge($rechnungAnhang, $holeBonAnhang()));
            }
        } catch (Throwable $e) {
            error_log('[AbholungKasse ' . ($wa['auftrag']['auftrag_nr'] ?? $aid) . '] ' . $e->getMessage());
        }
    }
}
