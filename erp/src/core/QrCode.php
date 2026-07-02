<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use Endroid\QrCode\QrCode as EndroidQrCode;
use Endroid\QrCode\Writer\PngWriter;

/**
 * QrCode – kleiner Helfer rund um endroid/qr-code
 *
 * Erzeugt QR-Codes bewusst live bei jedem Druck aus dem schon gespeicherten
 * Inhalt (z.B. kassen_bons.rksv_qr) statt sie als Bilddatei abzulegen —
 * die Erzeugung dauert nur wenige Millisekunden, ein eigenes Datei-Handling
 * (Pfade, Aufräumen, Speicherplatz) würde keinen echten Vorteil bringen.
 */
class QrCode
{
    public static function dataUri(string $inhalt, int $groesse = 160): string
    {
        $qrCode = new EndroidQrCode(data: $inhalt, size: $groesse, margin: 4);
        $writer = new PngWriter();
        return $writer->write($qrCode)->getDataUri();
    }
}
