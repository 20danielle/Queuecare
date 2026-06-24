<?php
/**
 * gestionnaire.php — Point d'entrée espace gestionnaire
 * Routeur principal pour toutes les actions des gestionnaires
 *
 * ACCÈS : UNIQUEMENT les gestionnaires (ni médecin, ni admin)
 */

// Configuration PHP en tout premier (avant tout output)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/helpers/AuthHelper.php';

// L'inscription et la connexion sont publiques : pas de vérification d'accès
$action = $_GET['action'] ?? 'dashboard';

// Actions AJAX qui doivent retourner du JSON même en cas de session expirée
$ajaxActions = [
    'get_dashboard_data', 'traiter_action_ajax',
    'get_planning', 'get_emploi_temps',
    'get_qrcode_actif', 'generer_qrcode',
    'get_profil_data', 'mettre_a_jour_profil', 'verifier_mdp',
    'api_sous_services', 'get_historique'
];
$isAjax = in_array($action, $ajaxActions);

// Buffer pour les actions AJAX
if ($isAjax) {
    ob_start();
}

if (!in_array($action, ['inscription', 'connexion'])) {
    if (!AuthHelper::estGestionnaire()) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Session expirée, veuillez vous reconnecter.', 'redirect' => 'gestionnaire.php?action=connexion']);
            exit;
        }
        header('Location: accueil.php');
        exit;
    }
    $sessionOk = AuthHelper::verifierSession(null);
    if (!$sessionOk) {
        if ($isAjax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Session expirée, veuillez vous reconnecter.', 'redirect' => 'gestionnaire.php?action=connexion']);
            exit;
        }
        header('Location: gestionnaire.php?action=connexion&timeout=1');
        exit;
    }
}

$allowedActions = [
    // Authentification
    'inscription', 'connexion', 'dashboard', 'deconnexion',
    // QR Code
    'generer_qrcode', 'get_qrcode_actif', 'telecharger_qrcode',
    // Planning
    'get_planning', 'get_emploi_temps',
    // Actions AJAX
    'get_dashboard_data', 'traiter_action_ajax',
    // Profil
    'get_profil_data', 'mettre_a_jour_profil', 'verifier_mdp',
    // Historique
    'get_historique'
];

if (!in_array($action, $allowedActions)) {
    http_response_code(404);
    die('Page introuvable.');
}

require_once __DIR__ . '/controllers/GestionnaireController.php';
$ctrl = new GestionnaireController();

// QRCodeController chargé uniquement pour les actions QR (évite les warnings phpqrcode)
$qrActions = ['generer_qrcode', 'get_qrcode_actif', 'telecharger_qrcode'];
if (in_array($action, $qrActions)) {
    require_once __DIR__ . '/controllers/QRCodeController.php';
    $qrCtrl = new QRCodeController();
}

switch ($action) {
    case 'inscription':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ctrl->traiterInscription();
        } else {
            $ctrl->afficherInscription();
        }
        break;

    case 'connexion':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ctrl->traiterConnexion();
        } else {
            $ctrl->afficherConnexion();
        }
        break;

    case 'dashboard':
        $ctrl->afficherDashboard();
        break;

    case 'deconnexion':
        $ctrl->deconnecter();
        break;

    case 'generer_qrcode':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $qrCtrl->genererQRCode();
        } else {
            $qrCtrl->afficherGenerateur();
        }
        break;

    case 'get_qrcode_actif':
        $qrCtrl->getQRCodeActif();
        break;

    case 'telecharger_qrcode':
        $qrCtrl->telechargerQRCode();
        break;

    case 'get_planning':
        $ctrl->getPlanning();
        break;

    case 'get_emploi_temps':
        $ctrl->getEmploiTemps();
        break;

    case 'get_dashboard_data':
        $ctrl->getDashboardData();
        break;

    case 'traiter_action_ajax':
        $ctrl->traiterActionAjax();
        break;

    case 'get_profil_data':
        $ctrl->getProfilData();
        break;

    case 'mettre_a_jour_profil':
        $ctrl->mettreAJourProfil();
        break;

    case 'verifier_mdp':
        $ctrl->verifierMdp();
        break;

    case 'get_historique':
        $ctrl->getHistorique();
        break;

    default:
        $ctrl->afficherDashboard();
        break;
}