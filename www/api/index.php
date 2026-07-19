<?php
ini_set('display_errors', '0');
error_reporting(E_ALL);








date_default_timezone_set('Africa/Douala');
ob_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    require_once __DIR__ . '/config/database.php';
    require_once __DIR__ . '/config/smtp.php';
    require_once __DIR__ . '/config/helpers.php';

    $method = $_SERVER['REQUEST_METHOD'];
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
    $uri = rtrim($uri, '/');
    $uri = preg_replace('#^(/www)?/api#', '', $uri);

    if ($uri === '/auth/login/patient' && $method === 'POST') {
        require __DIR__ . '/auth/login.php';
    } elseif ($uri === '/auth/login/google/patient' && $method === 'POST') {
        require __DIR__ . '/auth/google_login.php';
    } elseif ($uri === '/auth/register/patient' && $method === 'POST') {
        require __DIR__ . '/auth/register.php';
    } elseif ($uri === '/auth/password/forgot' && $method === 'POST') {
        require __DIR__ . '/auth/forgot_password.php';
    } elseif ($uri === '/auth/password/reset' && $method === 'POST') {
        require __DIR__ . '/auth/reset_password.php';
    } elseif ($uri === '/auth/fcm/update' && $method === 'POST') {
        require __DIR__ . '/auth/update_fcm.php';
    } elseif ($uri === '/services' && $method === 'GET') {
        require __DIR__ . '/services/list.php';
    } elseif (preg_match('#^/services/(\d+)/sous-services$#', $uri, $m) && $method === 'GET') {
        $_GET['service_id'] = $m[1];
        require __DIR__ . '/services/sous_services.php';
    } elseif ($uri === '/consultations' && $method === 'GET') {
        require __DIR__ . '/consultations/history.php';
    } elseif (preg_match('#^/consultations/creneaux-disponibles/(\d+)$#', $uri, $m) && $method === 'GET') {
        $_GET['sub_service_id'] = $m[1];
        require __DIR__ . '/consultations/creneaux_disponibles.php';
    } elseif ($uri === '/consultations/scanner-qr' && $method === 'POST') {
        require __DIR__ . '/consultations/scan_qr.php';
    } elseif ($uri === '/consultations/rdv-distance' && $method === 'POST') {
        require __DIR__ . '/consultations/remote_appointment.php';
    } elseif (preg_match('#^/consultations/(\d+)/rang$#', $uri, $m) && $method === 'GET') {
        $_GET['consultation_id'] = $m[1];
        require __DIR__ . '/consultations/queue_status.php';
    } elseif (preg_match('#^/consultations/(\d+)/annuler$#', $uri, $m) && $method === 'POST') {
        $_GET['consultation_id'] = $m[1];
        require __DIR__ . '/consultations/cancel.php';
    } elseif (preg_match('#^/notifications/historique/(\d+)$#', $uri, $m) && $method === 'GET') {
        $_GET['patient_id'] = $m[1];
        require __DIR__ . '/notifications/history.php';
    } else {
        jsonError("Route introuvable : $method $uri", 'NOT_FOUND', 404);
    }
} catch (Throwable $e) {
    if (ob_get_length()) {
        ob_end_clean();
    }

    jsonError(
        'Erreur interne du serveur: ' . $e->getMessage(),
        'SERVER_ERROR',
        500
    );
}
