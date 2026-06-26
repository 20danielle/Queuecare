<?php
/**
 * config/app.php — Constantes globales QueueCare
 * À inclure en tête de index.php et admin.php
 */

// Version
define('APP_VERSION', '1.0.0');
define('APP_NAME', 'QueueCare');

// Sécurité : nombre max d'admins autorisés (1 directeur par instance)
define('ADMIN_MAX', 1);

// Durée de session (secondes) — 15 min
define('SESSION_TIMEOUT', 900);

// ── Mot de passe oublié ──────────────────────────────────────────────────
// Adresse et nom utilisés comme expéditeur des emails de réinitialisation
define('MAIL_FROM_ADDRESS', 'no-reply@queuecare.cm');
define('MAIL_FROM_NAME', 'QueueCare');

// Durée de validité du code envoyé par email (minutes)
define('RESET_CODE_DUREE_MIN', 15);

// Longueur du code de réinitialisation (chiffres)
define('RESET_CODE_LONGUEUR', 6);