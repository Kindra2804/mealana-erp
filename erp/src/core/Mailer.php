<?php
require_once __DIR__ . '/Firmenlogo.php';

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    private array $config;
    /** Absender/SMTP des Kanals (shops-Zeile) oder null = globaler Standard. Siehe fuerShop(). */
    private ?array $shop = null;
    /** Partner-Auftrag (Migration 237): Firmenname/Kontakt/Bank in den Mails = Partner. Siehe fuerPartnerAuftrag(). */
    private ?array $partner = null;

    public function __construct()
    {
        $db   = Database::getInstance();
        $this->config = $db->query("
            SELECT schluessel, wert FROM system_einstellungen
            WHERE schluessel LIKE 'mail_%'
               OR schluessel LIKE 'social_%'
               OR schluessel IN (
                   'firmenname','iban','bic','bank_name',
                   'strasse','plz','ort','tel','telefon',
                   'firma_web','website','email'
               )
        ")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Mails dieses Mailers laufen unter dem Absender des Kanals (Einstellungen → Mail → Absender je Kanal).
     * Leere Felder des Kanals = globaler Standard. Eigenes SMTP nur, wenn beim Kanal ein SMTP-Host steht.
     * Ohne Aufruf (oder mit null/0) bleibt alles wie bisher: globaler Absender, Hauptlogo.
     * Für Auftragsmails immer mit auftraege.shop_id aufrufen (Kasse/Hauptkanal haben meist leere Felder → global).
     */
    public function fuerShop(?int $shopId): self
    {
        $this->shop = null;
        if (!$shopId) return $this;
        $stmt = Database::getInstance()->prepare("
            SELECT id, name, wc_url, mail_from_name, mail_from_address, mail_smtp_host, mail_smtp_port,
                   mail_smtp_user, mail_smtp_pass, mail_smtp_encryption
            FROM shops WHERE id = ?
        ");
        $stmt->execute([$shopId]);
        $this->shop = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        return $this;
    }

    /**
     * Partner-Auftrag (Migration 237): Firmenname, Telefon, Website und Bankdaten in Kopf/Fußzeile der Mail
     * kommen vom Partner (Verkauf in dessen Namen). $auftrag = Zeile aus auftraege (partner_id oder id genügt).
     * Ohne Partner bleibt alles wie bisher.
     */
    public function fuerPartnerAuftrag(array $auftrag): self
    {
        $this->partner = null;
        $db = Database::getInstance();
        $pid = $auftrag['partner_id'] ?? null;
        if ($pid === null && (!empty($auftrag['id']) || !empty($auftrag['auftrag_id']))) {
            $st = $db->prepare("SELECT partner_id FROM auftraege WHERE id = ?");
            $st->execute([(int)($auftrag['auftrag_id'] ?? $auftrag['id'])]);
            $pid = $st->fetchColumn() ?: null;
        }
        if ($pid) {
            $st = $db->prepare("SELECT * FROM partner WHERE id = ?");
            $st->execute([(int)$pid]);
            $this->partner = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        return $this;
    }

    /** Wert des Kanals, wenn gesetzt, sonst der globale Wert. */
    private function wert(string $schluessel, string $standard = ''): string
    {
        $kanal = trim((string)($this->shop[$schluessel] ?? ''));
        if ($kanal !== '') return $kanal;
        return (string)($this->config[$schluessel] ?? $standard);
    }

    /** true = der Kanal hat einen eigenen SMTP-Server eingetragen. */
    private function eigenesSmtp(): bool
    {
        return trim((string)($this->shop['mail_smtp_host'] ?? '')) !== '';
    }

    /** Absender, der tatsächlich verwendet wird: ['name' => …, 'adresse' => …, 'smtp' => Host] (Anzeige/Test). */
    public function absender(): array
    {
        return [
            // Partner-Auftrag ohne Kanal-Absendernamen: Partner statt Hauptfirma (Verkauf im fremden Namen)
            'name'    => trim((string)($this->shop['mail_from_name'] ?? '')) ?: ($this->partner['name'] ?? '')
                         ?: $this->wert('mail_from_name', $this->config['firmenname'] ?? 'MeaLana ERP'),
            'adresse' => $this->wert('mail_from_address'),
            'smtp'    => (string)(($this->eigenesSmtp() ? $this->shop : $this->config)['mail_smtp_host'] ?? ''),
        ];
    }

    /**
     * Lädt das Shop-Logo als Base64-String (für Mail-Templates).
     * Aufrufer übergeben den Wert als 'logo_base64' in den Template-Variablen.
     */
    public function ladeShopLogo(int $shopId = 0): string
    {
        return Firmenlogo::base64(Firmenlogo::fuerShop($shopId));   // 0 = Hauptlogo
    }

    /**
     * Sendet eine HTML-Mail. Wirft Exception bei Fehler.
     *
     * @param string $empfaenger  Ziel-E-Mail-Adresse
     * @param string $betreff
     * @param string $htmlBody    HTML-Inhalt
     * @param string $textBody    Plaintext-Fallback
     */
    /**
     * @param bool $erzwinge  true = sendet auch wenn mail_aktiv=0 (für Test-Mail)
     */
    /**
     * @param array $anhaenge  [['pfad' => '/abs/path.pdf', 'name' => 'Dateiname.pdf'], ...]
     * @param bool  $erzwinge  true = sendet auch wenn mail_aktiv=0 (für Test-Mail)
     */
    public function sende(
        string $empfaenger,
        string $betreff,
        string $htmlBody,
        string $textBody = '',
        bool   $erzwinge = false,
        array  $anhaenge = []
    ): void {
        $mailAktiv    = ($this->config['mail_aktiv']       ?? '0') === '1';
        $testAdresse  =  trim($this->config['mail_test_adresse'] ?? '');

        if (!$erzwinge && !$mailAktiv) {
            if ($testAdresse) {
                // Testmodus: Mail an Test-Adresse umleiten statt verwerfen
                $betreff    = '[TEST an ' . $empfaenger . '] ' . $betreff;
                $empfaenger = $testAdresse;
            } else {
                error_log("[Mailer] Mail NICHT gesendet (deaktiviert, kein Test-Empfänger): An={$empfaenger} Betreff={$betreff}");
                return;
            }
        }

        $mail = new PHPMailer(true);

        // SMTP: eigener Server des Kanals (alle Felder vom Kanal) oder der globale
        $quelle = $this->eigenesSmtp() ? $this->shop : $this->config;
        $mail->isSMTP();
        $mail->Host       = (string)($quelle['mail_smtp_host'] ?? '');
        $mail->SMTPAuth   = true;
        $mail->Username   = (string)($quelle['mail_smtp_user'] ?? '');
        $mail->Password   = (string)($quelle['mail_smtp_pass'] ?? '');
        $mail->Port       = (int)($quelle['mail_smtp_port'] ?? 0) ?: 587;
        $mail->CharSet    = 'UTF-8';

        $enc = $quelle['mail_smtp_encryption'] ?? null;
        if ($enc === null) $enc = $this->config['mail_smtp_encryption'] ?? 'tls';
        $mail->SMTPSecure = match($enc) {
            'ssl'  => PHPMailer::ENCRYPTION_SMTPS,
            'tls'  => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };

        ['name' => $fromName, 'adresse' => $fromAddress] = $this->absender();

        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($empfaenger);
        $mail->isHTML(true);
        $mail->Subject = $betreff;
        $htmlBody      = self::bilderEinbetten($mail, $htmlBody);
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody ?: strip_tags($htmlBody);

        foreach ($anhaenge as $anhang) {
            if (!empty($anhang['pfad']) && file_exists($anhang['pfad'])) {
                $mail->addAttachment($anhang['pfad'], $anhang['name'] ?? basename($anhang['pfad']));
            }
        }

        $mail->send();
    }

    /**
     * data:-Bilder (Logo, Artikelbilder) als eingebettete Bilder (CID) anhängen — Gmail und
     * viele Webmailer zeigen data:-URIs nicht an. Gleiches Bild = gleiche CID (nur einmal).
     */
    public static function bilderEinbetten(PHPMailer $mail, string $html): string
    {
        $eingebettet = [];
        return preg_replace_callback('/src="data:(image\/(?:png|jpe?g|gif));base64,([^"]+)"/', function ($m) use ($mail, &$eingebettet) {
            $cid = md5($m[2]) . '@mealana';
            if (!isset($eingebettet[$cid])) {
                $datei = substr($cid, 0, 12) . ($m[1] === 'image/png' ? '.png' : '.jpg');
                $mail->addStringEmbeddedImage(base64_decode($m[2]), $cid, $datei, PHPMailer::ENCODING_BASE64, $m[1]);
                $eingebettet[$cid] = true;
            }
            return 'src="cid:' . $cid . '"';
        }, $html);
    }

    /**
     * Rendert ein Twig-Template und sendet es als Mail.
     *
     * @param array $anhaenge  [['pfad' => '/abs/path.pdf', 'name' => 'Dateiname.pdf'], ...]
     */
    public function sendeTemplate(
        string $empfaenger,
        string $betreff,
        string $templatePfad,
        array  $variablen = [],
        array  $anhaenge  = []
    ): void {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../templates');
        $twig   = new \Twig\Environment($loader);

        // Globale Firma-/Social-Daten als Defaults — caller-Werte in $variablen haben Vorrang
        $defaults = [
            'firmenname'       => $this->config['firmenname']       ?? 'MEALANA KG',
            'logo_base64'      => '',
            'firma_web'        => $this->config['firma_web']  ?? $this->config['website']  ?? '',
            'firma_tel'        => $this->config['tel']       ?? $this->config['telefon']  ?? '',
            'firma_iban'       => $this->config['iban']             ?? '',
            'firma_bic'        => $this->config['bic']              ?? '',
            'firma_bank'       => $this->config['bank_name']        ?? '',
            'social_instagram' => $this->config['social_instagram'] ?? '',
            'social_facebook'  => $this->config['social_facebook']  ?? '',
            'social_tiktok'    => $this->config['social_tiktok']    ?? '',
            'social_youtube'   => $this->config['social_youtube']   ?? '',
            'social_pinterest' => $this->config['social_pinterest'] ?? '',
        ];
        $vars = array_merge($defaults, $variablen);
        // Kontaktadresse („Bei Fragen … unter …“): einige Aufrufer lasen einen nie vorhandenen
        // Schlüssel firma_email → leer. Ersatz: Absender-Adresse, sonst Firmen-Mail (Shop-Test 2026-11-13)
        if (trim((string)($vars['firma_email'] ?? '')) === '') {
            $vars['firma_email'] = ($this->config['mail_from_address'] ?? '') ?: ($this->config['email'] ?? '');
        }

        // „marke“ = Name für Gruß/Anrede („Ihr …-Team“, „Bestellung bei …“). firmenname bleibt die
        // rechtliche Firma (Kontoinhaber, Fußzeile).
        $vars['marke'] = $vars['marke'] ?? $vars['firmenname'];

        // Kanal mit eigenem Absender: Kontakt-Adresse, Website, Marke und Logo des Shops statt der Hauptdaten.
        // Die Aufrufer übergeben firma_email meist selbst (globale Adresse) → hier bewusst überschrieben.
        if ($this->shop) {
            $kanalAdresse = trim((string)($this->shop['mail_from_address'] ?? ''));
            $kanalName    = trim((string)($this->shop['mail_from_name'] ?? ''));
            if ($kanalAdresse !== '') {
                $vars['firma_email'] = $kanalAdresse;
                if (trim((string)($this->shop['wc_url'] ?? '')) !== '') $vars['firma_web'] = rtrim($this->shop['wc_url'], '/');
            }
            if ($kanalName !== '') $vars['marke'] = $kanalName;
            if ($vars['logo_base64'] === '') $vars['logo_base64'] = $this->ladeShopLogo((int)$this->shop['id']);
        }

        // Partner-Auftrag: rechtliche Firma = Partner (Name, Kontakt, Bankverbindung für Vorkasse)
        if ($this->partner) {
            $p = $this->partner;
            $vars['firmenname'] = $p['name'];
            // Gruß „Ihr …-Team“: Kanalname (z.B. INDRA DESIGN) hat Vorrang, sonst Partner – nie die Hauptfirma
            if (trim((string)($this->shop['mail_from_name'] ?? '')) === '') $vars['marke'] = $p['name'];
            if (!empty($p['telefon'])) $vars['firma_tel'] = $p['telefon'];
            if (!empty($p['website'])) $vars['firma_web'] = (str_starts_with($p['website'], 'http') ? '' : 'https://') . $p['website'];
            if (!empty($p['iban'])) {
                $vars['firma_iban'] = $p['iban'];
                $vars['firma_bic']  = $p['bic'] ?? '';
                $vars['firma_bank'] = $p['bank_name'] ?? '';
            }
        }

        $htmlBody = $twig->render($templatePfad, $vars);

        $this->sende($empfaenger, $betreff, $htmlBody, '', false, $anhaenge);
    }
}
