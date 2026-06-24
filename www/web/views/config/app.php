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
