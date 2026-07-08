<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/modules/kasse/MesseSyncService.php';
require_once __DIR__ . '/../../src/modules/kasse/BfrService.php';
require_once __DIR__ . '/../../src/modules/arbeitsplatz/ArbeitsplatzService.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/core/Mailer.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragRepository.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';
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
        'bezeichnung'         => trim($p['bezeichnung'] ?? ''),
        'ean'                 => $p['ean']   ?? null,
        'menge'               => (float)($p['menge'] ?? 1),
        'einzelpreis_brutto'  => (float)($p['einzelpreis_brutto'] ?? 0),
        'steuer_prozent'      => (float)($p['steuer_prozent'] ?? 20),
        'rabatt_prozent'      => (float)($p['rabatt_prozent'] ?? 0),
        'charge'                       => $p['charge'] ?? null,
        'nachzutragen_lagerbestand_id' => isset($p['nachzutragen_lagerbestand_id']) ? (int)$p['nachzutragen_lagerbestand_id'] : null,
        'block'               => !empty($p['vonAuftrag']) ? 'auftrag' : ($p['block'] ?? null),
        'auftrag_position_id' => isset($p['auftrag_position_id']) ? (int)$p['auftrag_position_id'] : null,
        'retour_von_position_id' => isset($p['retour_von_position_id']) ? (int)$p['retour_von_position_id'] : null,
    ];
}

// Bruttobetrag serverseitig aus Positionen neu berechnen (kein Vertrauen auf Client-Wert)
$serverBrutto = 0;
foreach ($sauberePositionen as $p) {
    $serverBrutto += $p['menge'] * $p['einzelpreis_brutto'] * (1 - $p['rabatt_prozent'] / 100);
}
$bonDaten['bruttobetrag'] = round($serverBrutto, 2);

$webAuftragId     = isset($input['web_auftrag_id']) && $input['web_auftrag_id']
    ? (int)$input['web_auftrag_id'] : null;
$webAuftragStatus = $input['web_auftrag_status']    ?? null;
$webMitnehmen     = $input['web_auftrag_mitnehmen'] ?? null;

$nurAbschliessen      = ($input['nur_abschliessen'] ?? false) === true;
$webAuftragZahlStatus = $input['web_auftrag_zahlungsstatus'] ?? null;
$webAuftragBezahlt    = ($webAuftragZahlStatus === 'bezahlt');

// ── Manager-Override: Barauszahlung bei Retour eines bereits bezahlten Web-Auftrags ──
// Läuft VOR jeder Buchung (erstelleBon() folgt erst weiter unten), damit bei fehlender
// Freigabe wirklich nichts passiert — kein halb gebuchter Bon, keine Lagerbewegung.
if (!$nurAbschliessen && $webAuftragBezahlt && $webAuftragId) {
    $vorabStmt = Database::getInstance()->prepare("SELECT bruttobetrag FROM auftraege WHERE id = ?");
    $vorabStmt->execute([$webAuftragId]);
    $vorabBruttobetrag = (float)($vorabStmt->fetchColumn() ?: 0);

    $vorabAuftragAnteil = 0.0;
    foreach ($sauberePositionen as $bp) {
        if (!empty($bp['auftrag_position_id'])) {
            $vorabAuftragAnteil += $bp['menge'] * $bp['einzelpreis_brutto'] * (1 - $bp['rabatt_prozent'] / 100);
        }
    }
    $vorabAuftragAnteil = round($vorabAuftragAnteil, 2);
    if ($vorabAuftragAnteil <= 0) {
        $vorabAuftragAnteil = $vorabBruttobetrag;
    }
    $vorabRetourBetrag = round($vorabBruttobetrag - $vorabAuftragAnteil, 2);

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
        $bp['kein_lagerabzug'] = ($webAuftragStatus === 'abholbereit' || $webMitnehmen === false);
    } elseif ($bp['block'] === 'retour') {
        $bp['kein_lagerabzug'] = true; // Packplatz hat schon ausgebucht; Rücklagerung erfolgt über menge_geliefert-Tracking
    }
}
unset($bp);

// ── Kein-Bon-Abschluss: Auftrag bereits bezahlt, exakt abgeholt ──────────────
if ($nurAbschliessen && $webAuftragId) {
    try {
        $db    = Database::getInstance();
        $repo  = new AuftragRepository();
        $aStmt = $db->prepare("SELECT * FROM auftraege WHERE id = ?");
        $aStmt->execute([$webAuftragId]);
        $auftrag = $aStmt->fetch(PDO::FETCH_ASSOC);
        if (!$auftrag) throw new RuntimeException('Auftrag nicht gefunden');

        $lagerId  = (int)($bonDaten['lager_id'] ?? 1);
        $lagerSvc = new LagerService();

        $bonAuftragPos = [];
        foreach ($sauberePositionen as $bp) {
            if (!empty($bp['auftrag_position_id'])) {
                $bonAuftragPos[(int)$bp['auftrag_position_id']] = (float)$bp['menge'];
            }
        }

        $origPosStmt = $db->prepare("SELECT id, artikel_id, menge, charge FROM auftrag_positionen WHERE auftrag_id = ?");
        $origPosStmt->execute([$webAuftragId]);
        $origPositionen = $origPosStmt->fetchAll(PDO::FETCH_ASSOC);

        $alleGeliefert = true;
        foreach ($origPositionen as $op) {
            $imBon   = (float)($bonAuftragPos[$op['id']] ?? $op['menge']);
            $gepackt = (float)$op['menge'];
            $rueck   = $gepackt - $imBon;
            if ($rueck > 0.001 && !empty($op['artikel_id'])) {
                $lagerSvc->wareneingang([
                    'artikel_id'  => (int)$op['artikel_id'],
                    'lager_id'    => $lagerId,
                    'menge'       => $rueck,
                    'charge'      => $op['charge'] ?? null,
                    'referenz'    => 'Nicht abgeholt — Auftrag ' . $auftrag['auftrag_nr'],
                    'notiz'       => 'Abholung Kasse ohne Bon',
                    'benutzer_id' => $benutzerId,
                ]);
            }
            $db->prepare("UPDATE auftrag_positionen SET menge_geliefert = ? WHERE id = ?")->execute([$imBon, $op['id']]);
            if ($imBon < $gepackt) $alleGeliefert = false;
        }

        $neuerLieferStatus = $alleGeliefert ? 'abgeschlossen' : 'teilgeliefert';
        $db->prepare("UPDATE auftraege SET lieferstatus = ?, aktualisiert_am = NOW() WHERE id = ?")->execute([$neuerLieferStatus, $webAuftragId]);
        $repo->logStatus($webAuftragId,
            ['lieferstatus' => [$auftrag['lieferstatus'], $neuerLieferStatus]],
            'Abgeholt an Kasse — bereits bezahlt — kein Kassenbon', $benutzerId
        );

        if ($alleGeliefert) {
            $kunde = json_decode($auftrag['kunden_snapshot'] ?? '{}', true) ?: [];
            $email = trim($kunde['email'] ?? '');
            if ($email) {
                $firma = $db->query("SELECT schluessel, wert FROM system_einstellungen")->fetchAll(PDO::FETCH_KEY_PAIR);
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
                        'bon_nr'         => '',
                        'firma_email'    => $firma['mail_from_address'] ?? '',
                    ],
                    anhaenge: [],
                );
            }
        }

        echo json_encode(['erfolg' => true, 'auftrag_nr' => $auftrag['auftrag_nr']]);
    } catch (Throwable $e) {
        error_log('[AbholungOhneBon] ' . $e->getMessage());
        echo json_encode(['erfolg' => false, 'fehler' => 'Fehler beim Abschließen: ' . $e->getMessage()]);
    }
    exit;
}

// ── Für Bon-Erstellung: bei bereits bezahltem Auftrag nur Extra+Retour-Positionen ──
$bonErstellungPositionen = $sauberePositionen;
if ($webAuftragBezahlt && $webAuftragId) {
    $bonErstellungPositionen = array_values(array_filter($sauberePositionen, fn($p) => empty($p['auftrag_position_id'])));
    $bruttoBon = 0.0;
    foreach ($bonErstellungPositionen as $p) {
        $bruttoBon += $p['menge'] * $p['einzelpreis_brutto'] * (1 - $p['rabatt_prozent'] / 100);
    }
    $bonDaten['bruttobetrag'] = round($bruttoBon, 2);
}

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
    $chk = Database::getInstance()->prepare("SELECT menge, menge_retourniert, bezeichnung FROM auftrag_positionen WHERE id = ?");
    foreach ($retourAnfrageProPosition as $pid => $angefragteMenge) {
        $chk->execute([$pid]);
        $origPos = $chk->fetch(PDO::FETCH_ASSOC);
        if (!$origPos) continue;
        $nochOffen = (int)$origPos['menge'] - (int)$origPos['menge_retourniert'];
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

$result = $service->erstelleBon($bonDaten, $bonErstellungPositionen, $benutzerId);
echo json_encode($result);

// ─── Web-Auftrag abschließen ───────────────────────────────────────────────────
if ($result['erfolg'] && $webAuftragId) {
    try {
        $db    = Database::getInstance();
        $repo  = new AuftragRepository();
        $bonId = $result['bon_id'] ?? null;
        $bonNr = $result['bon_nr'] ?? '';

        $aStmt = $db->prepare("SELECT * FROM auftraege WHERE id = ?");
        $aStmt->execute([$webAuftragId]);
        $auftrag = $aStmt->fetch(PDO::FETCH_ASSOC);

        if ($auftrag) {

            // ── K1 Kassen-Auftrag aufteilen ──────────────────────────────────
            // erstelleBon() erstellt immer einen K1-Auftrag mit ALLEN Bon-Positionen.
            // Strategie:
            //   Keine Extras → K1 löschen, Bon direkt auf Web-Auftrag zeigen
            //   Extras vorhanden → K1 behält nur die Extra-Positionen (separate Auftrag),
            //                      Web-Auftrag und K1 sind über den Bon verknüpft.
            $k1AuftragId = null;
            if ($bonId) {
                $k1Row = $db->prepare("SELECT auftrag_id FROM kassen_bons WHERE id = ?");
                $k1Row->execute([$bonId]);
                $k1AuftragId = (int)$k1Row->fetchColumn() ?: null;
            }

            // Extra-Positionen = Bon-Artikel ohne auftrag_position_id
            $extraPositionen = array_values(array_filter($sauberePositionen, fn($bp) =>
                !empty($bp['artikel_id']) && empty($bp['auftrag_position_id'])
            ));

            if ($k1AuftragId && $k1AuftragId !== $webAuftragId) {
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

                    $extraNetto  = 0.0;
                    $extraSteuer = 0.0;
                    $extraBrutto = 0.0;
                    foreach ($extraPositionen as $sortIdx => $ep) {
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
                            $k1AuftragId, $ep['artikel_id'], $ep['bezeichnung'], $ep['ean'],
                            $ep['menge'], $ep['menge'],
                            $nettEP, $ep['steuer_prozent'], $ep['rabatt_prozent'], $gesNetto, $sortIdx,
                        ]);
                        $steuerAnteil = $gesBrut - $gesBrut / (1 + $ep['steuer_prozent'] / 100);
                        $extraNetto  += $gesBrut / (1 + $ep['steuer_prozent'] / 100);
                        $extraSteuer += $steuerAnteil;
                        $extraBrutto += $gesBrut;
                    }

                    // K1-Beträge auf Extra-Summe korrigieren + Kunde vom Original-Auftrag übernehmen
                    $db->prepare("
                        UPDATE auftraege SET
                            nettobetrag = ?, steuerbetrag = ?, bruttobetrag = ?,
                            kassen_bon_id = ?, kunden_id = ?, kunden_snapshot = ?,
                            aktualisiert_am = NOW()
                        WHERE id = ?
                    ")->execute([
                        round($extraNetto, 2), round($extraSteuer, 2), round($extraBrutto, 2),
                        $bonId,
                        $auftrag['kunden_id'] ?: null,
                        $auftrag['kunden_snapshot'],  // immer kopieren — enthält den Namen für die Liste
                        $k1AuftragId,
                    ]);

                    // Bon: web_auftrag_id zeigt auf Web-Auftrag, auftrag_id bleibt K1
                    $db->prepare("UPDATE kassen_bons SET web_auftrag_id = ? WHERE id = ?")
                       ->execute([$webAuftragId, $bonId]);
                }
            }

            // ── Positions-Abgleich: was war tatsächlich im Bon? ───────────────
            // Bon-Positionen mit auftrag_position_id (= aus dem Auftrag geladen)
            $bonAuftragPos = [];
            foreach ($sauberePositionen as $bp) {
                if (!empty($bp['auftrag_position_id'])) {
                    $bonAuftragPos[(int)$bp['auftrag_position_id']] = $bp['menge'];
                }
            }

            // Retour-Positionen (block='retour') je ursprünglicher Position summieren —
            // für menge_retourniert, damit gutschrift_erstellen.php nicht nochmal dieselbe
            // Menge gutschreiben kann, die hier schon über die Kasse erstattet wurde.
            $retourProPosition = [];
            foreach ($sauberePositionen as $bp) {
                if ($bp['block'] === 'retour' && !empty($bp['retour_von_position_id'])) {
                    $pid = (int)$bp['retour_von_position_id'];
                    $retourProPosition[$pid] = ($retourProPosition[$pid] ?? 0) + abs($bp['menge']);
                }
            }

            // Alle Original-Positionen des Auftrags laden (inkl. artikel_id + charge für Rückbuchung)
            $origPosStmt = $db->prepare("SELECT id, artikel_id, menge, menge_geliefert, charge FROM auftrag_positionen WHERE auftrag_id = ?");
            $origPosStmt->execute([$webAuftragId]);
            $origPositionen = $origPosStmt->fetchAll(PDO::FETCH_ASSOC);

            // menge_geliefert aktualisieren + prüfen ob alle geliefert
            $alleGeliefert = true;
            $lagerId       = (int)($bonDaten['lager_id'] ?? 1);
            $lagerSvc      = new LagerService();

            foreach ($origPositionen as $op) {
                $imBon    = (float)($bonAuftragPos[$op['id']] ?? 0);
                $gepackt  = (float)$op['menge'];

                if (!empty($retourProPosition[$op['id']])) {
                    $db->prepare("UPDATE auftrag_positionen SET menge_retourniert = menge_retourniert + ? WHERE id = ?")
                       ->execute([$retourProPosition[$op['id']], $op['id']]);
                }

                if ($webAuftragStatus === 'abholbereit') {
                    // Packplatz hat menge_geliefert schon gesetzt — wir korrigieren nur die Differenz
                    $rueck = $gepackt - $imBon;
                    if ($rueck > 0.001 && !empty($op['artikel_id'])) {
                        // Nicht mitgenommene Artikel zurück ins Lager — mit Charge aus auftrag_positionen
                        $lagerSvc->wareneingang([
                            'artikel_id'  => (int)$op['artikel_id'],
                            'lager_id'    => $lagerId,
                            'menge'       => $rueck,
                            'charge'      => $op['charge'] ?? null,
                            'referenz'    => 'Rückgabe Kasse — Bon ' . $bonNr,
                            'notiz'       => 'Abholbereit, aber nicht mitgenommen — Auftrag ' . $auftrag['auftrag_nr'],
                            'benutzer_id' => $benutzerId,
                        ]);
                    }
                    // menge_geliefert auf tatsächlich mitgenommene Menge korrigieren
                    $db->prepare("UPDATE auftrag_positionen SET menge_geliefert = ? WHERE id = ?")
                       ->execute([$imBon, $op['id']]);
                    if ($imBon < $gepackt) $alleGeliefert = false;
                } else {
                    $neu = (float)($op['menge_geliefert'] ?? 0) + $imBon;
                    $db->prepare("UPDATE auftrag_positionen SET menge_geliefert = ? WHERE id = ?")
                       ->execute([$neu, $op['id']]);
                    if ($neu < $gepackt) $alleGeliefert = false;
                }
            }

            // ── Bezahlten Betrag (nur Auftrag-Anteil) berechnen ──────────────
            $auftragAnteil = 0.0;
            foreach ($sauberePositionen as $bp) {
                if (!empty($bp['auftrag_position_id'])) {
                    $rab = 1 - ($bp['rabatt_prozent'] / 100);
                    $auftragAnteil += $bp['menge'] * $bp['einzelpreis_brutto'] * $rab;
                }
            }
            $auftragAnteil = round($auftragAnteil, 2);
            if ($auftragAnteil <= 0) {
                $auftragAnteil = (float)$auftrag['bruttobetrag'];
            }

            // ── Zahlung buchen ────────────────────────────────────────────────
            // $summeBezahltGesamt bleibt null wenn $webAuftragBezahlt (Retour-Zweig, siehe unten) —
            // dort wird der Zahlstatus über $retourBetrag entschieden, nicht über die Summe.
            $summeBezahltGesamt = null;
            if (!$webAuftragBezahlt) {
                $db->prepare("
                    INSERT INTO auftrag_zahlungen (auftrag_id, betrag, buchungsdatum, notiz, erfasst_von)
                    VALUES (?, ?, CURDATE(), ?, ?)
                ")->execute([$webAuftragId, $auftragAnteil, 'Bezahlt an der Kasse — Bon ' . $bonNr, $benutzerId]);

                // Kumulierte Summe ALLER Zahlungen (nicht nur dieser Transaktion!) entscheidet
                // über "vollständig bezahlt" — ein $auftragAnteil-Vergleich allein würde bei
                // mehreren Teilzahlungen oder Rundungsdifferenzen zwischen Positions- und
                // Kopfbetrag fälschlich dauerhaft auf 'teilbezahlt' stehen bleiben.
                $summeStmt = $db->prepare("SELECT COALESCE(SUM(betrag), 0) FROM auftrag_zahlungen WHERE auftrag_id = ?");
                $summeStmt->execute([$webAuftragId]);
                $summeBezahltGesamt = (float)$summeStmt->fetchColumn();
            } else {
                // Bereits bezahlt: nur Erstattung (negativen Betrag) buchen wenn Retour.
                // Direkt aus den block='retour'-Positionen berechnen — NICHT über
                // bruttobetrag-auftragAnteil, da Retour/Extra-Positionen bewusst keine
                // auftrag_position_id tragen und $auftragAnteil sonst immer auf den vollen
                // bruttobetrag zurückfällt (Retourbetrag würde immer 0 ergeben).
                $retourBetrag = 0.0;
                foreach ($sauberePositionen as $bp) {
                    if (($bp['block'] ?? null) === 'retour') {
                        $rab = 1 - ($bp['rabatt_prozent'] / 100);
                        $retourBetrag += abs($bp['menge']) * $bp['einzelpreis_brutto'] * $rab;
                    }
                }
                $retourBetrag = round($retourBetrag, 2);
                if ($retourBetrag > 0.005) {
                    $db->prepare("
                        INSERT INTO auftrag_zahlungen (auftrag_id, betrag, buchungsdatum, notiz, erfasst_von)
                        VALUES (?, ?, CURDATE(), ?, ?)
                    ")->execute([$webAuftragId, -$retourBetrag, 'Rückerstattung bar an der Kasse — Bon ' . $bonNr, $benutzerId]);
                }
            }

            // ── Status setzen ─────────────────────────────────────────────────
            if ($webAuftragStatus === 'abholbereit' || $webMitnehmen === true) {
                $neuerLieferStatus = $alleGeliefert ? 'abgeschlossen' : 'teilgeliefert';
                if ($webAuftragBezahlt) {
                    $neuerZahlStatus = isset($retourBetrag) && $retourBetrag > 0.005 ? 'erstattet' : 'bezahlt';
                } else {
                    $neuerZahlStatus = $summeBezahltGesamt >= (float)$auftrag['bruttobetrag'] - 0.01 ? 'bezahlt' : 'teilbezahlt';
                }

                $db->prepare("
                    UPDATE auftraege
                    SET zahlungsstatus = ?, lieferstatus = ?, aktualisiert_am = NOW()
                    WHERE id = ?
                ")->execute([$neuerZahlStatus, $neuerLieferStatus, $webAuftragId]);

                $repo->logStatus($webAuftragId,
                    ['zahlungsstatus' => [$auftrag['zahlungsstatus'], $neuerZahlStatus],
                     'lieferstatus'   => [$auftrag['lieferstatus'],   $neuerLieferStatus]],
                    ($webMitnehmen === true ? 'Mitgenommen' : 'Abgeholt') . ' und bezahlt an der Kasse — Bon ' . $bonNr,
                    $benutzerId
                );
            } else {
                // nur Zahlung — Lieferstatus unverändert. War der Auftrag schon bezahlt
                // (versendet/teilgeliefert/abgeschlossen-Retoure, siehe Redesign 2026-07-08),
                // entscheidet der echte Retourbetrag statt der Kumulierten-Summe-Formel —
                // die gilt nur für den "wird hier erstmals bezahlt"-Fall.
                if ($webAuftragBezahlt) {
                    $neuerZahlStatus = isset($retourBetrag) && $retourBetrag > 0.005 ? 'erstattet' : 'bezahlt';
                } else {
                    $neuerZahlStatus = $summeBezahltGesamt >= (float)$auftrag['bruttobetrag'] - 0.01 ? 'bezahlt' : 'teilbezahlt';
                }
                $db->prepare("
                    UPDATE auftraege SET zahlungsstatus = ?, aktualisiert_am = NOW() WHERE id = ?
                ")->execute([$neuerZahlStatus, $webAuftragId]);

                $repo->logStatus($webAuftragId,
                    ['zahlungsstatus' => [$auftrag['zahlungsstatus'], $neuerZahlStatus]],
                    ($webAuftragBezahlt ? 'Retoure an der Kasse' : 'Nur Zahlung an der Kasse — Versand/Abholung folgt') . ' — Bon ' . $bonNr,
                    $benutzerId
                );
            }

            // ── kassen_bon_id auf Auftrag setzen (sperrt Rechnung-Erstellung) ─
            // NUR wenn der Auftrag durch DIESE Transaktion überhaupt erst bezahlt/fakturiert
            // wird ($webAuftragBezahlt war beim Laden false) — war er schon vorher bezahlt
            // (z.B. eigene Rechnung, PayPal), darf ein späterer Retoure/Extra-Bon diese nicht
            // verdrängen. web_auftrag_id wird trotzdem immer gesetzt (reine Referenz, keine Sperre).
            if ($bonId) {
                if (!$webAuftragBezahlt) {
                    $db->prepare("UPDATE auftraege SET kassen_bon_id = ? WHERE id = ?")
                       ->execute([$bonId, $webAuftragId]);
                }
                $db->prepare("UPDATE kassen_bons SET web_auftrag_id = ? WHERE id = ?")
                   ->execute([$webAuftragId, $bonId]);
            }

            // ── Abholbestätigungs-Mail (nur bei vollständiger Übergabe) ──────
            if ($alleGeliefert) {
                $kunde = json_decode($auftrag['kunden_snapshot'] ?? '{}', true) ?: [];
                $email = trim($kunde['email'] ?? '');
                if ($email) {
                    if (!isset($firma)) {
                        $firma = $db->query("SELECT schluessel, wert FROM system_einstellungen")
                                     ->fetchAll(PDO::FETCH_KEY_PAIR);
                    }

                    // Bon als A4-Rechnung für Mail-Anhang generieren (statt schmalem 68mm-Bon)
                    $bonAnhang = [];
                    if ($bonId) {
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
                            'kunde_name'     => trim(($kunde['vorname'] ?? '') . ' ' . ($kunde['nachname'] ?? ''))
                                               ?: ($kunde['firma'] ?? ''),
                            'auftrag_nummer' => $auftrag['auftrag_nr'],
                            'bon_nr'         => $bonNr,
                            'firma_email'    => $firma['mail_from_address'] ?? '',
                        ],
                        anhaenge: $bonAnhang,
                    );
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[AbholungKasse] ' . $e->getMessage());
    }
}
