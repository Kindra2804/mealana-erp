<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/Database.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as MailException;

class Mailer
{
    private array $config;

    public function __construct()
    {
        $db   = Database::getInstance();
        $rows = $db->query("SELECT schluessel, wert FROM system_einstellungen
                            WHERE schluessel LIKE 'mail_%'
                               OR schluessel = 'firmenname'")
                   ->fetchAll(PDO::FETCH_KEY_PAIR);
        $this->config = $rows;
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
    public function sende(
        string $empfaenger,
        string $betreff,
        string $htmlBody,
        string $textBody = '',
        bool   $erzwinge = false
    ): void {
        if (!$erzwinge && ($this->config['mail_aktiv'] ?? '0') !== '1') {
            error_log("[Mailer] Mail NICHT gesendet (deaktiviert): An={$empfaenger} Betreff={$betreff}");
            return;
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

        $mail->send();
    }

    /**
     * Rendert ein Twig-Template und sendet es als Mail.
     */
    public function sendeTemplate(
        string $empfaenger,
        string $betreff,
        string $templatePfad,
        array  $variablen = []
    ): void {
        require_once __DIR__ . '/../../vendor/autoload.php';

        $loader = new \Twig\Loader\FilesystemLoader(__DIR__ . '/../../templates');
        $twig   = new \Twig\Environment($loader);

        $htmlBody = $twig->render($templatePfad, array_merge($variablen, [
            'firmenname' => $this->config['firmenname'] ?? 'MEALANA KG',
        ]));

        $this->sende($empfaenger, $betreff, $htmlBody);
    }
}
