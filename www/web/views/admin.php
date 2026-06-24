<?php
/**
 * admin.php — Point d'entrée espace administrateur (directeur)
 */

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/helpers/AuthHelper.php';
require_once __DIR__ . '/config/app.php';

if (!AuthHelper::estAdmin()) {
    header('Location: index.php?action=login&error=acces_refuse');
    exit;
}

AuthHelper::verifierSession('index.php?action=login');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$action = $_GET['action'] ?? 'dashboard';

$allowedActions = [
    'dashboard',
    'sauvegarder_hopital',
    'creer_utilisateur',
    'toggle_statut',
    'creer_ss', 'modifier_ss', 'supprimer_ss', 'toggle_statut_ss',
    // Planning
    'get_planning_medecin',
    'sauvegarder_planning_medecin',
    'get_planning_gestionnaire',
    'sauvegarder_planning_gestionnaire',
    // Profil admin
    'verifier_mdp_admin',
    'modifier_profil_admin',
    // Statistiques
    'get_stats_admin',
    'get_temps_attente_admin',
    'consultations_medecin',
    'activer_role_medecin',
    'action_consultation_admin',
];

if (!in_array($action, $allowedActions, true)) {
    http_response_code(404);
    die('Page introuvable.');
}

require_once __DIR__ . '/controllers/AdminController.php';
$ctrl = new AdminController();

switch ($action) {
    case 'dashboard':                    $ctrl->afficherDashboard(); break;
    case 'sauvegarder_hopital':          $ctrl->sauvegarderHopital(); break;
    case 'creer_utilisateur':
        ($_SERVER['REQUEST_METHOD'] === 'POST') ? $ctrl->creerUtilisateur() : $ctrl->afficherFormulaireUtilisateur();
        break;
    case 'toggle_statut':                $ctrl->toggleStatutUtilisateur(); break;
    case 'creer_ss':                     $ctrl->creerSousService(); break;
    case 'modifier_ss':                  $ctrl->modifierSousService(); break;
    case 'supprimer_ss':                 $ctrl->supprimerSousService(); break;
    case 'toggle_statut_ss':             $ctrl->toggleStatutSousService(); break;
    case 'get_planning_medecin':         $ctrl->getPlanningMedecin(); break;
    case 'sauvegarder_planning_medecin': $ctrl->sauvegarderPlanningMedecin(); break;
    case 'get_planning_gestionnaire':    $ctrl->getPlanningGestionnaire(); break;
    case 'sauvegarder_planning_gestionnaire': $ctrl->sauvegarderPlanningGestionnaire(); break;
    case 'verifier_mdp_admin':           $ctrl->verifierMdpAdmin(); break;
    case 'modifier_profil_admin':        $ctrl->modifierProfilAdmin(); break;
    case 'get_stats_admin':              $ctrl->getStatsAdmin(); break;
    case 'get_temps_attente_admin':      $ctrl->getTempsAttenteAdmin(); break;
    case 'consultations_medecin':          $ctrl->afficherConsultationsAdmin(); break;
    case 'activer_role_medecin':           $ctrl->activerRoleMedecin(); break;
    case 'action_consultation_admin':      $ctrl->actionConsultationAdmin(); break;
    default:                             $ctrl->afficherDashboard();
}