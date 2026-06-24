<?php
/**
 * Wrapper de compatibilité phpqrcode -> kazuhikoarase/qrcode-generator
 * Fournit l'interface QRcode::png() attendue par QRCodeController.php
 */

require_once __DIR__ . '/qrcode_kaz.php';

// Constantes de niveau de correction
if (!defined('QR_ECLEVEL_L')) define('QR_ECLEVEL_L', 1);
if (!defined('QR_ECLEVEL_M')) define('QR_ECLEVEL_M', 0);
if (!defined('QR_ECLEVEL_Q')) define('QR_ECLEVEL_Q', 3);
if (!defined('QR_ECLEVEL_H')) define('QR_ECLEVEL_H', 2);

if (!class_exists('QRcode')) {
class QRcode
{
    /**
     * Génère un QR code PNG
     * @param string $text    Contenu du QR code
     * @param string|false $outfile Chemin de sortie (false = stdout)
     * @param int $level      Niveau de correction (QR_ECLEVEL_*)
     * @param int $size       Taille de chaque module en pixels
     * @param int $margin     Marge en modules
     */
    public static function png(
        string $text,
        $outfile = false,
        int $level = QR_ECLEVEL_L,
        int $size = 3,
        int $margin = 4
    ): void {
        // Mapping des constantes vers l'API Kazuhiko
        $levelMap = [
            QR_ECLEVEL_L => 1, // ErrorCorrectLevel::L
            QR_ECLEVEL_M => 0, // ErrorCorrectLevel::M
            QR_ECLEVEL_Q => 3, // ErrorCorrectLevel::Q
            QR_ECLEVEL_H => 2, // ErrorCorrectLevel::H
        ];
        $errorLevel = $levelMap[$level] ?? 1;

        $qr = QRCodeKaz::getMinimumQRCode($text, $errorLevel);
        $image = $qr->createImage($size, $margin);

        if ($outfile === false) {
            header('Content-Type: image/png');
            imagepng($image);
        } else {
            // Créer le répertoire si nécessaire
            $dir = dirname($outfile);
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            imagepng($image, $outfile);
        }
        imagedestroy($image);
    }
}
} else {
    // Une classe QRcode/QRCode existe déjà ailleurs sur le serveur (collision de nom).
    // On ne peut pas redéclarer le wrapper attendu par QRCodeController : on log pour diagnostic.
    error_log('[phpqrcode] La classe QRcode existe déjà (définie ailleurs sur ce serveur) — le wrapper local n\'a pas pu être chargé.');
}
