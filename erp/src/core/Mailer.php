<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use PHPMailer\PHPMailer\PHPMailer;

class Mailer
{
    private array $config;

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
                   'firma_web','website'
               )
        ")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Lädt das Shop-Logo als Base64-String (für Mail-Templates).
     * Aufrufer übergeben den Wert als 'logo_base64' in den Template-Variablen.
     */
    public function ladeShopLogo(int $shopId = 1): string
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT logo_pfad FROM shops WHERE id = ?");
        $stmt->execute([$shopId]);
        $pfadRel = $stmt->fetchColumn() ?: 'img/logo.png';
        $pfad    = __DIR__ . '/../../public/' . $pfadRel;
        return file_exists($pfad) ? base64_encode(file_get_contents($pfad)) : '';
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

        $mail->isSMTP();
        $mail->Host       = $this->config['mail_smtp_host']       ?? '';
        $mail->SMTPAuth   = true;
        $mail->Username   = $this->config['mail_smtp_user']       ?? '';
        $mail->Password   = $this->config['mail_smtp_pass']       ?? '';
        $mail->Port       = (int)($this->config['mail_smtp_port'] ?? 587);
        $mail->CharSet    = 'UTF-8';

        $enc = $this->config['mail_smtp_encryption'] ?? 'tls';
        $mail->SMTPSecure = match($enc) {
            'ssl'  => PHPMailer::ENCRYPTION_SMTPS,
            'tls'  => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };

        $fromName    = $this->config['mail_from_name']    ?? ($this->config['firmenname'] ?? 'MeaLana ERP');
        $fromAddress = $this->config['mail_from_address'] ?? '';

        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($empfaenger);
        $mail->isHTML(true);
        $mail->Subject = $betreff;
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
        $htmlBody = $twig->render($templatePfad, array_merge($defaults, $variablen));

        $this->sende($empfaenger, $betreff, $htmlBody, '', false, $anhaenge);
    }
}
