<?php
/**
 * config/mail.php
 * Configuration SMTP — Compatible Railway (variables d'env) et WampServer local.
 *
 * En local (Wamp), remplis directement les valeurs par défaut ci-dessous.
 * En production (Railway), définis les variables d'environnement correspondantes
 * et laisse les valeurs par défaut vides ou de test.
 *
 * IMPORTANT (Gmail) :
 * - MAIL_SMTP_PASS doit être un "mot de passe d'application" Gmail,
 *   PAS ton mot de passe Gmail habituel. Voir explication fournie séparément.
 */

// envValue()/envInt() viennent normalement de config/database.php. On les
// redéfinit ici en garde au cas où ce fichier serait inclus seul (ex: admin.php).
if (!function_exists('envValue')) {
    function envValue(array $keys, string $default): string {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== '') {
                return $value;
            }
        }
        return $default;
    }
}

if (!function_exists('envInt')) {
    function envInt(array $keys, int $default): int {
        foreach ($keys as $key) {
            $value = getenv($key);
            if ($value !== false && $value !== '') {
                return (int) $value;
            }
        }
        return $default;
    }
}

define('MAIL_SMTP_HOST', envValue(['MAIL_SMTP_HOST', 'SMTP_HOST'], 'smtp.gmail.com'));
define('MAIL_SMTP_PORT', envInt(['MAIL_SMTP_PORT', 'SMTP_PORT'], 587));
define('MAIL_SMTP_USER', envValue(['MAIL_SMTP_USER', 'SMTP_USER'], 'angezutchi@gmail.com'));
define('MAIL_SMTP_PASS', envValue(['MAIL_SMTP_PASS', 'SMTP_PASS'], 'vgeandxeexipikxx'));

// Type de chiffrement : 'tls' (port 587, recommandé) ou 'ssl' (port 465)
define('MAIL_SMTP_SECURE', envValue(['MAIL_SMTP_SECURE', 'SMTP_SECURE'], 'tls'));

// Active les logs détaillés SMTP dans le journal d'erreurs PHP (utile en debug local)
define('MAIL_SMTP_DEBUG', envValue(['MAIL_SMTP_DEBUG', 'SMTP_DEBUG'], '0') === '1');