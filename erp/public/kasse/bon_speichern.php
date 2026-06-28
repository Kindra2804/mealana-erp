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
        'charge'              => $p['charge'] ?? null,
        'block'               => !empty($p['vonAuftrag']) ? 'auftrag' : null,
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

// Per-Position: kein_lagerabzug nur für Auftrag-Positionen die schon gebucht sind
// Frische Kasse-Artikel laufen immer normal durch (nie gesperrt)
foreach ($sauberePositionen as &$bp) {
    if ($bp['block'] === 'auftrag') {
        // abholbereit → Packplatz hat gebucht; nur_zahlung → Packplatz bucht später
        $bp['kein_lagerabzug'] = ($webAuftragStatus === 'abholbereit' || $webMitnehmen === false);
    }
}
unset($bp);

$result = $service->erstelleBon($bonDaten, $sauberePositionen, $benutzerId);
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

                    // K1-Beträge auf Extra-Summe korrigieren
                    $db->prepare("
                        UPDATE auftraege SET
                            nettobetrag = ?, steuerbetrag = ?, bruttobetrag = ?,
                            kassen_bon_id = ?, aktualisiert_am = NOW()
                        WHERE id = ?
                    ")->execute([
                        round($extraNetto, 2), round($extraSteuer, 2), round($extraBrutto, 2),
                        $bonId, $k1AuftragId,
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
            $db->prepare("
                INSERT INTO auftrag_zahlungen (auftrag_id, betrag, buchungsdatum, notiz, erfasst_von)
                VALUES (?, ?, CURDATE(), ?, ?)
            ")->execute([$webAuftragId, $auftragAnteil, 'Bezahlt an der Kasse — Bon ' . $bonNr, $benutzerId]);

            // ── Status setzen ─────────────────────────────────────────────────
            if ($webAuftragStatus === 'abholbereit' || $webMitnehmen === true) {
                $neuerLieferStatus = $alleGeliefert ? 'abgeschlossen' : 'teilgeliefert';
                $neuerZahlStatus   = $auftragAnteil >= (float)$auftrag['bruttobetrag'] ? 'bezahlt' : 'teilbezahlt';

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
                    $firma  = $db->query("SELECT schluessel, wert FROM system_einstellungen")
                                 ->fetchAll(PDO::FETCH_KEY_PAIR);
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
                    );
                }
            }
        }
    } catch (Throwable $e) {
        error_log('[AbholungKasse] ' . $e->getMessage());
    }
}
