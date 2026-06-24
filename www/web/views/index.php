<?php
/**
 * index.php — Point d'entrée principal
 * - API REST pour l'application mobile Kotlin
 * - Authentification web (connexion / déconnexion)
 * - Inscription admin (première configuration)
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Africa/Douala');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/app.php';

// Autoload simple des classes
spl_autoload_register(function ($class) {
    $base = __DIR__ . '/';
    $paths = [
        $base . 'controllers/' . $class . '.php',
        $base . 'models/'      . $class . '.php',
        $base . 'helpers/'     . $class . '.php',
    ];
    foreach ($paths as $file) {
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

$action = $_GET['action'] ?? '';

// ── Actions API (retournent du JSON) ────────────────────────────────────────
$apiActions = [
    'scanner_qr', 'get_ticket_status', 'get_consultation_status',
    'register_fcm_token', 'unregister_fcm_token', 'test_notification',
    'patient_login', 'patient_register', 'patient_logout',
    'patient_profile', 'patient_update_profile', 'patient_change_password',
    'patient_reset_password', 'patient_consultations', 'patient_tickets',
    'patient_notifications', 'mark_notification_read',
    'mark_all_notifications_read', 'health_check',
    'patient_creneaux_sous_service', 'patient_reserver_consultation',
    'patient_rang_consultation',
];

// ── Actions Web ──────────────────────────────────────────────────────────────
$webActions = ['login', 'logout', 'register_admin'];

// ─── API ────────────────────────────────────────────────────────────────────
if (!empty($action) && in_array($action, $apiActions)) {
    header('Content-Type: application/json; charset=utf-8');

    switch ($action) {
        case 'scanner_qr':
            require_once __DIR__ . '/controllers/QRCodeController.php';
            (new QRCodeController())->scannerQRCodeAPI();
            break;
        case 'get_ticket_status':
            require_once __DIR__ . '/controllers/TicketController.php';
            (new TicketController())->getTicketStatus();
            break;
        case 'get_consultation_status':
            require_once __DIR__ . '/controllers/ConsultationController.php';
            (new ConsultationController())->getConsultationStatus();
            break;
        case 'register_fcm_token':
            require_once __DIR__ . '/controllers/NotificationController.php';
            (new NotificationController())->registerFCMToken();
            break;
        case 'unregister_fcm_token':
            require_once __DIR__ . '/controllers/NotificationController.php';
            (new NotificationController())->unregisterFCMToken();
            break;
        case 'test_notification':
            require_once __DIR__ . '/controllers/NotificationController.php';
            (new NotificationController())->testNotification();
            break;
        case 'patient_login':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->login();
            break;
        case 'patient_register':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->register();
            break;
        case 'patient_logout':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->logout();
            break;
        case 'patient_profile':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->getProfile();
            break;
        case 'patient_update_profile':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->updateProfile();
            break;
        case 'patient_change_password':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->changePassword();
            break;
        case 'patient_reset_password':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->resetPassword();
            break;
        case 'patient_consultations':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->getConsultations();
            break;
        case 'patient_tickets':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->getTickets();
            break;
        case 'patient_notifications':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->getNotifications();
            break;
        case 'mark_notification_read':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->markNotificationRead();
            break;
        case 'mark_all_notifications_read':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->markAllNotificationsRead();
            break;
        case 'patient_creneaux_sous_service':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->getCreneauxSousService();
            break;
        case 'patient_reserver_consultation':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->reserverConsultation();
            break;
        case 'patient_rang_consultation':
            require_once __DIR__ . '/controllers/PatientController.php';
            (new PatientController())->getRangConsultation();
            break;
        case 'health_check':
            echo json_encode([
                'success'   => true,
                'status'    => 'online',
                'timestamp' => date('Y-m-d H:i:s'),
                'version'   => APP_VERSION,
            ]);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Action non implémentée']);
    }
    exit;
}

// ─── Web ────────────────────────────────────────────────────────────────────
if (!empty($action) && in_array($action, $webActions)) {
    require_once __DIR__ . '/controllers/AuthController.php';
    $auth = new AuthController();

    switch ($action) {
        case 'login':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $auth->login();
            } else {
                $auth->showLoginForm();
            }
            break;

        case 'logout':
            $auth->logout();
            break;

        case 'register_admin':
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $auth->registerAdmin();
            } else {
                $auth->showRegisterAdmin();
            }
            break;
    }
    exit;
}

// ─── Redirect si déjà connecté ──────────────────────────────────────────────
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    switch ($_SESSION['role']) {
        case 'admin':
            header('Location: admin.php');
            exit;
        case 'medecin':
            header('Location: medecin.php');
            exit;
        case 'gestionnaire':
            header('Location: gestionnaire.php');
            exit;
    }
}

// ─── Page d'accueil publique ─────────────────────────────────────────────────
require_once __DIR__ . '/accueil.php';