<?php
/**
 * helpers/MailHelper.php
 * Envoi d'emails via SMTP (PHPMailer), pour contourner l'absence de
 * sendmail/SMTP sur WampServer (la fonction native mail() y échoue
 * silencieusement car aucun MTA local n'est configuré).
 *
 * Configuration : voir config/mail.php (identifiants SMTP Gmail).
 */

require_once __DIR__ . '/../vendor/PHPMailer/src/Exception.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/PHPMailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if (!defined('MAIL_SMTP_HOST')) {
    require_once __DIR__ . '/../config/mail.php';
}

class MailHelper {

    /**
     * Génère un code numérique aléatoire (ex: "483920").
     */
    public static function genererCode(int $longueur = 6): string {
        $min = (int) str_pad('1', $longueur, '0');
        $max = (int) str_pad('', $longueur, '9');
        return (string) random_int($min, $max);
    }

    /**
     * Envoie le code de réinitialisation par email.
     *
     * @param string $email Destinataire
     * @param string $nom    Nom de l'utilisateur (affiché dans le mail)
     * @param string $code   Code à communiquer
     * @return bool true si l'email a été transmis au serveur de mail
     */
    public static function envoyerCodeReset(string $email, string $nom, string $code): bool {
        $appName = defined('APP_NAME') ? APP_NAME : 'QueueCare';
        $duree   = defined('RESET_CODE_DUREE_MIN') ? RESET_CODE_DUREE_MIN : 15;

        $sujet = "$appName — Votre code de réinitialisation";

        $corps = self::construireCorpsHtml($nom, $code, $duree, $appName);

        return self::envoyer($email, $sujet, $corps);
    }

    /**
     * Construit le contenu HTML de l'email.
     */
    private static function construireCorpsHtml(string $nom, string $code, int $duree, string $appName): string {
        $nomAffiche = htmlspecialchars($nom);
        $codeAffiche = htmlspecialchars($code);

        return "
        <div style=\"font-family:Arial,Helvetica,sans-serif;max-width:480px;margin:0 auto;\">
            <div style=\"background:#0d6efd;padding:24px;border-radius:10px 10px 0 0;text-align:center;\">
                <h1 style=\"color:#ffffff;font-size:20px;margin:0;\">$appName</h1>
            </div>
            <div style=\"background:#ffffff;padding:28px;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 10px 10px;\">
                <p style=\"color:#1e293b;font-size:15px;\">Bonjour $nomAffiche,</p>
                <p style=\"color:#1e293b;font-size:15px;\">
                    Vous avez demandé la réinitialisation de votre mot de passe.
                    Utilisez le code ci-dessous pour vous connecter :
                </p>
                <div style=\"text-align:center;margin:28px 0;\">
                    <span style=\"display:inline-block;background:#f0f4f8;color:#0d6efd;font-size:28px;
                                 font-weight:700;letter-spacing:6px;padding:14px 24px;border-radius:8px;\">
                        $codeAffiche
                    </span>
                </div>
                <p style=\"color:#64748b;font-size:13px;\">
                    Ce code est valable $duree minutes. Si vous n'êtes pas à l'origine de cette demande,
                    ignorez simplement cet email.
                </p>
            </div>
        </div>";
    }

    /**
     * Envoi via PHPMailer/SMTP. Retourne false en cas d'échec (identifiants
     * invalides, serveur SMTP injoignable, etc.) — l'erreur précise est
     * toujours écrite dans le journal PHP (error_log).
     */
    private static function envoyer(string $destinataire, string $sujet, string $corpsHtml): bool {
        $fromAddress = defined('MAIL_FROM_ADDRESS') ? MAIL_FROM_ADDRESS : 'no-reply@queuecare.cm';
        $fromName    = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'QueueCare';

        $mail = new PHPMailer(true);

        try {
            // Configuration du transport SMTP
            $mail->isSMTP();
            $mail->Host       = MAIL_SMTP_HOST;
            $mail->Port       = MAIL_SMTP_PORT;
            $mail->SMTPAuth   = true;
            $mail->Username   = MAIL_SMTP_USER;
            $mail->Password   = MAIL_SMTP_PASS;
            $mail->SMTPSecure = MAIL_SMTP_SECURE; // 'tls' (port 587) ou 'ssl' (port 465)
            $mail->CharSet    = 'UTF-8';

            if (MAIL_SMTP_DEBUG) {
                // 2 = affiche les échanges client<->serveur dans error_log, utile en debug local
                $mail->SMTPDebug   = 2;
                $mail->Debugoutput = function ($str) {
                    error_log('[MailHelper][SMTP] ' . $str);
                };
            }

            // Expéditeur / destinataire
            $mail->setFrom($fromAddress, $fromName);
            $mail->addAddress($destinataire);

            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $sujet;
            $mail->Body    = $corpsHtml;
            $mail->AltBody  = strip_tags($corpsHtml);

            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log("[MailHelper] Échec d'envoi à $destinataire : " . $mail->ErrorInfo);
            return false;
        } catch (\Throwable $e) {
            error_log("[MailHelper] Erreur inattendue lors de l'envoi à $destinataire : " . $e->getMessage());
            return false;
        }
    }
}