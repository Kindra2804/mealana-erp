<?php

require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Mailer.php';
require_once __DIR__ . '/DokumentService.php';

/**
 * RechnungMailService – verschickt eine (Teil-)Rechnung per Mail an den Kunden.
 *
 * Eine Stelle für Packplatz, Kasse (vorab bezahlte Abholung) und den manuellen
 * Button im Auftrag (vorher doppelt in abschliessen.php und dokument_erstellen.php).
 * Zahlungsstatus/offener Betrag gelten für DIESE Rechnung (Zahlungen werden auf
 * Teilrechnungen in Reihenfolge verteilt, siehe DokumentService::zahlungsInfo()).
 */
class RechnungMailService
{
    /** @return bool true = Mail verschickt, false = Kunde ohne E-Mail-Adresse */
    public static function sende(int $rechnungId, array $weitereAnhaenge = []): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT r.*, a.auftrag_nr, a.kunden_snapshot, a.shop_id
            FROM rechnungen r JOIN auftraege a ON a.id = r.auftrag_id
            WHERE r.id = ?
        ");
        $stmt->execute([$rechnungId]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$r) return false;

        $kunde = json_decode($r['kunden_snapshot'] ?? '{}', true) ?: [];
        $email = trim($kunde['email'] ?? '');
        if (!$email) return false;

        $dokumentService = new DokumentService();
        $info = $dokumentService->zahlungsInfo($rechnungId);
        $pfad = $dokumentService->getDateipfad((int)$r['auftrag_id'], (string)$r['dateiname']);

        $status = $info['offen'] <= 0.004 ? 'bezahlt' : ($info['angerechnet'] > 0.004 ? 'teilbezahlt' : 'ausstehend');
        $name   = trim(($kunde['vorname'] ?? '') . ' ' . ($kunde['nachname'] ?? '')) ?: ($kunde['firma'] ?? $email);
        $firmaEmail = $db->query("SELECT wert FROM system_einstellungen WHERE schluessel = 'mail_from_address'")->fetchColumn() ?: '';

        $mailer = new Mailer();
        $mailer->sendeTemplate(
            empfaenger:   $email,
            betreff:      'Ihre Rechnung ' . $r['rechnung_nr'],
            templatePfad: 'mails/rechnung_mail.html.twig',
            variablen: [
                'logo_base64'    => $mailer->ladeShopLogo((int)($r['shop_id'] ?? 1)),
                'anrede'         => $kunde['anrede'] ?? '',
                'nachname'       => $kunde['nachname'] ?? '',
                'kunde_name'     => $name,
                'auftrag_nummer' => $r['auftrag_nr'],
                'rechnung_nr'    => $r['rechnung_nr'],
                'brutto_gesamt'  => (float)$r['bruttobetrag'],
                'faellig_datum'  => $r['faellig_am'] ? date('d.m.Y', strtotime($r['faellig_am'])) : '',
                'firma_email'    => $firmaEmail,
                'zahlungsstatus' => $status,
                'zahlungen'      => array_map(fn($z) => [
                    'buchungsdatum' => $z['datum'],
                    'betrag'        => $z['betrag'],
                    'notiz'         => $z['text'],
                ], $info['zahlungen']),
                'offener_betrag' => $info['offen'],
            ],
            anhaenge: array_merge([['pfad' => $pfad, 'name' => $r['rechnung_nr'] . '.pdf']], $weitereAnhaenge),
        );
        return true;
    }
}
