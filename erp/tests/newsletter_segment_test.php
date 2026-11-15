<?php
// Test Newsletter-Segmente gegen echte Dev-Kunden/Aufträge (nur lesend) mit eigenen Test-Empfängern (quelle TEST_NL5),
// die am Ende wieder gelöscht werden. Aufruf: php tests/newsletter_segment_test.php
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/core/Database.php';
require_once __DIR__ . '/../src/core/Encryption.php';
require_once __DIR__ . '/../src/modules/newsletter/NewsletterSegmentService.php';

$db = Database::getInstance();
$svc = new NewsletterSegmentService();
$fehler = 0;
$ok = function (string $t, bool $b) use (&$fehler) { echo ($b ? 'OK   ' : 'FEHL ') . $t . "\n"; if (!$b) $fehler++; };
$shop = 4;
if ((int)$db->query("SELECT COUNT(*) FROM newsletter_empfaenger WHERE shop_id = $shop AND quelle <> 'TEST_NL5'")->fetchColumn() > 0) {
    exit("Shop $shop hat echte Empfänger — Test abgebrochen.\n");
}

// Kunden: mit 2–6 Aufträgen, jüngster Kauf neu / älter; ein Schläfer (> 30 Monate)
$kunden = $db->query("SELECT k.id FROM kunden k JOIN auftraege au ON au.kunden_id = k.id
                      WHERE k.email_hash IS NOT NULL AND k.status = 'aktiv' AND au.zahlungsstatus <> 'storniert'
                      GROUP BY k.id HAVING COUNT(DISTINCT au.id) BETWEEN 2 AND 6 AND MAX(au.erstellt_am) > NOW() - INTERVAL 12 MONTH
                      ORDER BY MAX(au.erstellt_am) DESC LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
$schlaefer = (int)$db->query("SELECT k.id FROM kunden k JOIN auftraege au ON au.kunden_id = k.id WHERE k.email_hash IS NOT NULL AND k.status = 'aktiv'
                              GROUP BY k.id HAVING MAX(au.erstellt_am) < NOW() - INTERVAL 30 MONTH ORDER BY MAX(au.erstellt_am) DESC LIMIT 1")->fetchColumn();
$alleKunden = array_merge(array_map('intval', $kunden), [$schlaefer]);

try {
    $ins = $db->prepare("INSERT INTO newsletter_empfaenger (shop_id, email_enc, email_hash, kunden_id, status, quelle, eingetragen_am, optin_am, token)
                         VALUES (?, ?, ?, ?, 'aktiv', 'TEST_NL5', NOW() - INTERVAL 10 DAY, NOW() - INTERVAL 10 DAY, ?)");
    foreach ($alleKunden as $i => $kid) {
        $hash = $db->query("SELECT email_hash FROM kunden WHERE id = $kid")->fetchColumn();
        // der erste bekommt absichtlich KEINE Zuordnung → muss über die E-Mail nachgezogen werden
        $ins->execute([$shop, Encryption::encrypt("test$i@nl5.invalid"), $hash, $i === 0 ? null : $kid, bin2hex(random_bytes(16))]);
    }
    $ins->execute([$shop, Encryption::encrypt('fremd@nl5.invalid'), Encryption::hash('fremd@nl5.invalid'), null, bin2hex(random_bytes(16))]);

    $z = fn($s) => $svc->zaehlen($shop, $svc->normalisieren($s))['treffer'];
    $ok('ohne Segment: 4', $z([]) === 4);
    $ok('leeres Segment = null', $svc->normalisieren(['kunde' => '', 'laender' => '']) === null);
    $ok('Kunden: 3 (inkl. nachgezogener Zuordnung)', $z(['kunde' => 'kunden']) === 3);
    $ok('Zuordnung nachgezogen', (int)$db->query("SELECT COUNT(*) FROM newsletter_empfaenger WHERE quelle = 'TEST_NL5' AND kunden_id IS NOT NULL")->fetchColumn() === 3);
    $ok('Nicht-Kunden: 1', $z(['kunde' => 'nicht_kunden']) === 1);

    $erwAt = (int)$db->query("SELECT COUNT(DISTINCT kunde_id) FROM kunden_adressen WHERE land = 'AT' AND kunde_id IN (" . implode(',', $alleKunden) . ")")->fetchColumn();
    $ok("Land AT: $erwAt", $z(['laender' => 'at']) === $erwAt);
    $ok('Land XX: 0', $z(['laender' => 'XX']) === 0);

    $ok('Kauf in 12 Monaten: 2', $z(['letzter_kauf' => 'innerhalb', 'letzter_monate' => 12]) === 2);
    $ok('Schläfer > 24 Monate: 1', $z(['letzter_kauf' => 'nicht_innerhalb', 'letzter_monate' => 24]) === 1);

    // Kategorie: eine Wurzelkategorie eines gekauften Artikels des ersten Kunden
    $kat = (int)$db->query("SELECT ak.kategorie_id FROM auftraege au JOIN auftrag_positionen ap ON ap.auftrag_id = au.id JOIN artikel a ON a.id = ap.artikel_id
                            JOIN artikel_kategorien ak ON ak.artikel_id IN (a.id, COALESCE(a.vaterartikel_id, a.id))
                            WHERE au.kunden_id = {$alleKunden[0]} AND au.zahlungsstatus <> 'storniert' AND au.erstellt_am > NOW() - INTERVAL 24 MONTH LIMIT 1")->fetchColumn();
    if ($kat) {
        // Wurzel suchen → Unterkategorien müssen mitzählen
        $wurzel = $kat;
        while ($p = $db->query("SELECT parent_id FROM kategorien WHERE id = $wurzel")->fetchColumn()) $wurzel = (int)$p;
        $ok("Kategorie $kat: mind. 1", $z(['kategorien' => [$kat]]) >= 1);
        $ok("Wurzelkategorie $wurzel (Unterkategorien): mind. 1", $z(['kategorien' => [$wurzel]]) >= 1);
        $ok('Kategorie (Kauf letzte 24 Mon.) + Schläfer (nichts in 24 Mon.) schließen sich aus: 0', $z(['kategorien' => [$wurzel], 'letzter_kauf' => 'nicht_innerhalb', 'letzter_monate' => 24]) === 0);
    } else {
        echo "(kein Kategorie-Kauf gefunden — Kategorie-Test übersprungen)\n";
    }
    $her = (int)$db->query("SELECT COALESCE(a.hersteller_id, v.hersteller_id) FROM auftraege au JOIN auftrag_positionen ap ON ap.auftrag_id = au.id JOIN artikel a ON a.id = ap.artikel_id
                            LEFT JOIN artikel v ON v.id = a.vaterartikel_id WHERE au.kunden_id IN ({$alleKunden[0]}, {$alleKunden[1]}) AND au.zahlungsstatus <> 'storniert'
                            AND au.erstellt_am > NOW() - INTERVAL 24 MONTH AND COALESCE(a.hersteller_id, v.hersteller_id) IS NOT NULL LIMIT 1")->fetchColumn();
    if ($her) $ok("Hersteller $her: mind. 1", $z(['hersteller' => [$her]]) >= 1);
    $ok('angemeldet seit morgen: 0', $z(['angemeldet_seit' => date('Y-m-d', strtotime('+1 day'))]) === 0);
    $ok('angemeldet seit vor 30 Tagen: 4', $z(['angemeldet_seit' => date('Y-m-d', strtotime('-30 days'))]) === 4);
    $ok('Kombination Kunden + AT + Kauf 12 Mon. ≤ 2', $z(['kunde' => 'kunden', 'laender' => 'AT', 'letzter_kauf' => 'innerhalb', 'letzter_monate' => 12]) <= 2);
    echo 'Beschreibung: ' . $svc->beschreibung($svc->normalisieren(['kunde' => 'kunden', 'laender' => 'AT, DE', 'kategorien' => $kat ? [$kat] : [], 'letzter_kauf' => 'nicht_innerhalb', 'letzter_monate' => 18])) . "\n";
} finally {
    $n = $db->exec("DELETE FROM newsletter_empfaenger WHERE quelle = 'TEST_NL5'");
    echo "aufgeräumt: $n Test-Empfänger\n";
}
echo $fehler ? "$fehler FEHLER\n" : "alles OK\n";
