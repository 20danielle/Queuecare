#!/usr/bin/env php
<?php
/**
 * cron/rappels_30min.php
 *
 * Script à exécuter toutes les 5 minutes via cron pour envoyer
 * automatiquement les rappels de proximité (≤30 min) aux patients.
 *
 * Configuration cron (crontab -e) :
 *   *\/5 * * * * php /chemin/vers/projet/cron/rappels_30min.php >> /var/log/queuecare_cron.log 2>&1
 *
 * Ce script peut aussi être appelé via HTTP (protégé par un token secret) :
 *   GET /cron/rappels_30min.php?secret=VOTRE_SECRET
 */

define('ROOT', dirname(__DIR__));

// Fuseau horaire fixe : Douala (WAT, UTC+1, pas de changement d'heure).
// Doit être défini avant tout appel à date()/time() dans ce script (les
// crons n'ont pas de php.ini web et peuvent démarrer en UTC par défaut).
date_default_timezone_set('Africa/Douala');

// Protection HTTP (optionnelle mais recommandée si accessible en web)
if (php_sapi_name() !== 'cli') {
    $secret = getenv('CRON_SECRET') ?: 'changeme_secret_cron';
    if (($_GET['secret'] ?? '') !== $secret) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: application/json');
}

require_once ROOT . '/config/database.php';
require_once ROOT . '/config/firebase.php';
require_once ROOT . '/helpers/NotificationHelper.php';
require_once ROOT . '/models/NotificationModel.php';
require_once ROOT . '/models/PatientModel.php';
require_once ROOT . '/helpers/QueueNotificationService.php';

$start = microtime(true);

try {
    $svc    = new QueueNotificationService();
    $result = $svc->processRappels30Min();

    $duration = round((microtime(true) - $start) * 1000);
    $log = sprintf(
        "[%s] Rappels 30min — envoyés: %d, ignorés: %d (%d ms)",
        date('Y-m-d H:i:s'),
        $result['rappels_envoyes'],
        $result['rappels_ignores'],
        $duration
    );

    error_log($log);

    if (php_sapi_name() !== 'cli') {
        echo json_encode(array_merge($result, ['duration_ms' => $duration, 'status' => 'ok']));
    } else {
        echo $log . PHP_EOL;
    }

} catch (Throwable $e) {
    $msg = '[CRON ERROR] ' . $e->getMessage();
    error_log($msg);
    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    } else {
        echo $msg . PHP_EOL;
        exit(1);
    }
}