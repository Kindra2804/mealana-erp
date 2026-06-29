<?php
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/kasse/KassenService.php';
require_once __DIR__ . '/../../src/core/Database.php';
require_once __DIR__ . '/../../src/core/Mailer.php';
require_once __DIR__ . '/../../src/modules/auftraege/AuftragRepository.php';
require_once __DIR__ . '/../../src/modules/lager/LagerService.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['erfolg' => false, 'fehler' => 'Nur POST erlaubt.']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['erfolg' => false, 'fehler' => 'Ungültige Daten.']); exit;
}

$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);
$service    = new KassenService();

$bonDaten = [
    'kasse_id'         => (int)($input['kasse_id']         ?? 1),
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

        $origPosStmt = $db->prepare("SELECT id, artikel_id, menge FROM auftrag_positionen WHERE auftrag_id = ?");
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

            // Alle Original-Positionen des Auftrags laden (inkl. artikel_id für Rückbuchung)
            $origPosStmt = $db->prepare("SELECT id, artikel_id, menge, menge_geliefert FROM auftrag_positionen WHERE auftrag_id = ?");
            $origPosStmt->execute([$webAuftragId]);
            $origPositionen = $origPosStmt->fetchAll(PDO::FETCH_ASSOC);

            // menge_geliefert aktualisieren + prüfen ob alle geliefert
            $alleGeliefert = true;
            $lagerId       = (int)($bonDaten['lager_id'] ?? 1);
            $lagerSvc      = new LagerService();

            foreach ($origPositionen as $op) {
                $imBon    = (float)($bonAuftragPos[$op['id']] ?? 0);
                $gepackt  = (float)$op['menge'];

                if ($webAuftragStatus === 'abholbereit') {
                    // Packplatz hat menge_geliefert schon gesetzt — wir korrigieren nur die Differenz
                    $rueck = $gepackt - $imBon;
                    if ($rueck > 0.001 && !empty($op['artikel_id'])) {
                        // Nicht mitgenommene Artikel zurück ins Lager buchen
                        $lagerSvc->wareneingang([
                            'artikel_id'  => (int)$op['artikel_id'],
                            'lager_id'    => $lagerId,
                            'menge'       => $rueck,
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
            if (!$webAuftragBezahlt) {
                $db->prepare("
                    INSERT INTO auftrag_zahlungen (auftrag_id, betrag, buchungsdatum, notiz, erfasst_von)
                    VALUES (?, ?, CURDATE(), ?, ?)
                ")->execute([$webAuftragId, $auftragAnteil, 'Bezahlt an der Kasse — Bon ' . $bonNr, $benutzerId]);
            } else {
                // Bereits bezahlt: nur Erstattung (negativen Betrag) buchen wenn Retour
                $retourBetrag = round((float)$auftrag['bruttobetrag'] - $auftragAnteil, 2);
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
                    $neuerZahlStatus = $auftragAnteil >= (float)$auftrag['bruttobetrag'] ? 'bezahlt' : 'teilbezahlt';
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
                // nur Zahlung — Lieferstatus unverändert
                $neuerZahlStatus = $auftragAnteil >= (float)$auftrag['bruttobetrag'] ? 'bezahlt' : 'teilbezahlt';
                $db->prepare("
                    UPDATE auftraege SET zahlungsstatus = ?, aktualisiert_am = NOW() WHERE id = ?
                ")->execute([$neuerZahlStatus, $webAuftragId]);

                $repo->logStatus($webAuftragId,
                    ['zahlungsstatus' => [$auftrag['zahlungsstatus'], $neuerZahlStatus]],
                    'Nur Zahlung an der Kasse — Bon ' . $bonNr . ' — Versand/Abholung folgt',
                    $benutzerId
                );
            }

            // ── kassen_bon_id auf Auftrag setzen (sperrt Rechnung-Erstellung) ─
            if ($bonId) {
                $db->prepare("UPDATE auftraege SET kassen_bon_id = ? WHERE id = ?")
                   ->execute([$bonId, $webAuftragId]);
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

                    // Bon-PDF für Mail-Anhang generieren
                    $bonAnhang = [];
                    if ($bonId) {
                        try {
                            require_once __DIR__ . '/../../vendor/autoload.php';
                            $bonDaten = $service->getBon((int)$bonId);
                            if ($bonDaten) {
                                // Steuer-Totale
                                $steuerTotale = [];
                                foreach ($bonDaten['positionen'] as $p) {
                                    $mg  = abs((float)$p['menge']);
                                    $br  = $mg * (float)$p['einzelpreis_brutto'] * (1 - (float)$p['rabatt_prozent'] / 100);
                                    $stz = (float)$p['steuer_prozent'];
                                    $nt  = $br / (1 + $stz / 100);
                                    $kk  = number_format($stz, 0);
                                    $steuerTotale[$kk] = $steuerTotale[$kk] ?? ['satz' => $stz, 'netto' => 0, 'steuer' => 0];
                                    $steuerTotale[$kk]['netto']  += $nt;
                                    $steuerTotale[$kk]['steuer'] += ($br - $nt);
                                }

                                $fnm  = htmlspecialchars($firma['firmenname'] ?? 'MeaLana');
                                $brtto = abs((float)$bonDaten['bruttobetrag']);
                                $zlbl = ['bar' => 'Bar', 'karte_extern' => 'Karte (extern)', 'gutschein' => 'Gutschein', 'kombi' => 'Bar + Karte'][$bonDaten['zahlungsart']] ?? $bonDaten['zahlungsart'];

                                $h  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
                                $h .= '<style>@page{margin:5mm}*{box-sizing:border-box}body{font-family:"Courier New",monospace;font-size:10px;width:68mm;margin:0;padding:0;color:#000}.z{text-align:center}.b{font-weight:bold}.l{border-top:1px dashed #000;margin:2px 0}.r{display:flex;justify-content:space-between;margin:1px 0}.s{font-size:8px;color:#444;padding-left:3px}</style>';
                                $h .= '</head><body>';
                                $h .= '<div class="z b" style="font-size:12px">' . $fnm . '</div>';
                                if (!empty($firma['firma_strasse'])) $h .= '<div class="z">' . htmlspecialchars($firma['firma_strasse']) . '</div>';
                                if (!empty($firma['firma_ort']))     $h .= '<div class="z">' . htmlspecialchars($firma['firma_ort']) . '</div>';
                                if (!empty($firma['firma_uid']))     $h .= '<div class="z">UID: ' . htmlspecialchars($firma['firma_uid']) . '</div>';
                                $h .= '<div class="l"></div>';
                                $h .= '<div class="r"><span>Bon-Nr.:</span><span class="b">' . htmlspecialchars($bonDaten['bon_nr']) . '</span></div>';
                                $h .= '<div class="r"><span>Datum:</span><span>' . date('d.m.Y H:i', strtotime($bonDaten['erstellt_am'])) . '</span></div>';
                                $h .= '<div class="l"></div>';

                                foreach ($bonDaten['positionen'] as $pos) {
                                    $mg  = (float)$pos['menge'];
                                    $pr  = (float)$pos['einzelpreis_brutto'];
                                    $rab = (float)$pos['rabatt_prozent'];
                                    $gs  = $mg * $pr * (1 - $rab / 100);
                                    $h .= '<div class="r"><span>' . htmlspecialchars(mb_substr($pos['bezeichnung'], 0, 26)) . '</span><span class="b">€ ' . number_format(abs($gs), 2, ',', '.') . '</span></div>';
                                    $h .= '<div class="s">' . abs($mg) . '× €' . number_format(abs($pr), 2, ',', '.') . ($rab > 0 ? ' -' . number_format($rab, 0) . '%' : '') . ' · ' . number_format((float)$pos['steuer_prozent'], 0) . '% MwSt</div>';
                                }

                                $h .= '<div class="l"></div>';
                                foreach ($steuerTotale as $kk => $st) {
                                    $h .= '<div class="r" style="font-size:8px"><span>Netto ' . $kk . '%</span><span>€ ' . number_format($st['netto'], 2, ',', '.') . '</span></div>';
                                    $h .= '<div class="r" style="font-size:8px"><span>USt ' . $kk . '%</span><span>€ ' . number_format($st['steuer'], 2, ',', '.') . '</span></div>';
                                }
                                $h .= '<div class="l"></div>';
                                $h .= '<div class="r b" style="font-size:13px"><span>GESAMT</span><span>€ ' . number_format($brtto, 2, ',', '.') . '</span></div>';
                                $h .= '<div class="r"><span>Zahlungsart:</span><span>' . htmlspecialchars($zlbl) . '</span></div>';

                                if ($bonDaten['zahlungsart'] === 'bar' && $bonDaten['gegeben'] !== null) {
                                    $h .= '<div class="r"><span>Gegeben:</span><span>€ ' . number_format((float)$bonDaten['gegeben'], 2, ',', '.') . '</span></div>';
                                    $h .= '<div class="r b"><span>Rückgeld:</span><span>€ ' . number_format((float)($bonDaten['rueckgeld'] ?? 0), 2, ',', '.') . '</span></div>';
                                } elseif ($bonDaten['zahlungsart'] === 'kombi') {
                                    $h .= '<div class="r"><span>Karte:</span><span>€ ' . number_format((float)$bonDaten['karten_betrag'], 2, ',', '.') . '</span></div>';
                                    $h .= '<div class="r"><span>Bar:</span><span>€ ' . number_format((float)$bonDaten['bar_betrag'], 2, ',', '.') . '</span></div>';
                                    if ((float)($bonDaten['rueckgeld'] ?? 0) > 0) {
                                        $h .= '<div class="r b"><span>Rückgeld:</span><span>€ ' . number_format((float)$bonDaten['rueckgeld'], 2, ',', '.') . '</span></div>';
                                    }
                                }
                                $h .= '<div class="l"></div>';
                                $h .= '<div class="z">Danke für Ihren Einkauf!</div>';
                                $h .= '</body></html>';

                                $opt = new \Dompdf\Options();
                                $opt->set('defaultFont', 'DejaVu Sans');
                                $opt->set('isRemoteEnabled', false);
                                $dom = new \Dompdf\Dompdf($opt);
                                $dom->loadHtml($h, 'UTF-8');
                                $dom->setPaper([0, 0, 226.77, 566.93], 'portrait'); // 80mm × 200mm
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
