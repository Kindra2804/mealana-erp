<?php
// Dropshipping-Aktionen aus der Auftrags-Detailseite: bestellen / versendet / stornieren
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../../src/modules/dropshipping/DropshippingService.php';

$auftragId = (int)($_POST['auftrag_id'] ?? 0);
$zurueck   = 'Location: detail.php?id=' . $auftragId;
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$auftragId) {
    header('Location: liste.php');
    exit;
}

$service    = new DropshippingService();
$benutzerId = (int)($_SESSION['benutzer']['id'] ?? 0);
$aktion     = $_POST['aktion'] ?? '';

// Bestellung muss zu diesem Auftrag gehören (Schutz gegen vertauschte IDs)
$bestellung = $service->bestellungZuAuftrag($auftragId);
$bestellungId = (int)($_POST['bestellung_id'] ?? 0);
if (in_array($aktion, ['versendet', 'stornieren'], true) && (!$bestellung || (int)$bestellung['id'] !== $bestellungId)) {
    $_SESSION['fehler'] = ['Dropship-Bestellung passt nicht zu diesem Auftrag.'];
    header($zurueck);
    exit;
}

switch ($aktion) {
    case 'bestellen':
        $pauschaleRoh = str_replace(',', '.', trim($_POST['pauschale'] ?? ''));
        $pauschale    = $pauschaleRoh === '' ? null : (float)$pauschaleRoh;
        $erg = $service->bestellen($auftragId, $benutzerId, $pauschale);
        if ($erg['erfolg']) {
            $_SESSION['erfolg'] = 'Dropship-Bestellung angelegt — jetzt im Händlerportal bestellen (Daten unten zum Kopieren).';
        }
        break;

    case 'versendet':
        $erg = $service->versendetMelden($bestellungId, $_POST['tracking'] ?? '', $_POST['versanddienstleister'] ?? 'manuell', $benutzerId);
        if ($erg['erfolg']) {
            $text = 'Als versendet gebucht (ohne Lagerbuchung).';
            if ($erg['rechnung']) {
                $text .= ' Rechnung ' . $erg['rechnung']['rechnung_nr']
                    . ($erg['mail_ok'] ? ' per Mail an den Kunden geschickt.' : ' erstellt — nicht per Mail verschickt, bitte ausdrucken.');
                if (!$erg['mail_ok']) {
                    $_SESSION['dokument_oeffnen'] = BASE_PATH . '/auftraege/dokument_download.php?auftrag_id=' . $auftragId
                        . '&datei=' . urlencode($erg['rechnung']['dateiname']);
                }
            }
            $_SESSION['erfolg'] = $text;
            if ($erg['hinweis']) $_SESSION['fehler'] = [$erg['hinweis']];
        }
        break;

    case 'stornieren':
        $erg = $service->stornieren($bestellungId, $benutzerId);
        if ($erg['erfolg']) {
            $_SESSION['erfolg'] = 'Dropship-Bestellung storniert — der Auftrag geht wieder normal über den Packplatz.';
        }
        break;

    default:
        $erg = ['erfolg' => false, 'fehler' => 'Unbekannte Aktion.'];
}

if (!$erg['erfolg']) {
    $_SESSION['fehler'] = [$erg['fehler']];
}
header($zurueck);
exit;
