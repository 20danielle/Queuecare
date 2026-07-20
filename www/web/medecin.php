<?php
/**
 * medecin.php — Point d'entrée espace médecin
 * Routeur principal pour toutes les actions des médecins
 * 
 * ACCÈS : Médecins et Administrateurs (Directeur)
 */

// Fuseau horaire fixe : Douala (WAT, UTC+1, pas de changement d'heure).
// Doit être défini avant tout appel à date()/time() dans cette requête.
date_default_timezone_set('Africa/Douala');

session_start();
require_once __DIR__ . '/helpers/AuthHelper.php';
require_once __DIR__ . '/helpers/LangHelper.php';
LangHelper::init();

// L'inscription est publique : pas de vérification d'accès
$action = $_GET['action'] ?? 'dashboard';

$ajaxActions = [
    'api_sous_services',
    'get_consultations_data', 'get_stats_data',
    'demarrer_consultation_ajax', 'terminer_consultation_ajax',
    'marquer_absent_ajax', 'annuler_toutes_ajax',
    'mettre_en_pause_ajax', 'reprendre_consultation_ajax',
    'get_planning_medecin',
    'get_profil_data', 'mettre_a_jour_profil', 'verifier_mdp',
    'get_historique',
    'get_creneaux_disponibles',
    'fixer_prochain_rdv_ajax',
    'get_stats_evolution',
    'get_temps_attente_evolution',
    'get_dashboard_medecin_data',
    'changer_langue',
];
$isAjax = in_array($action, $ajaxActions, true);

if ($action !== 'inscription') {
    // Vérification des droits d'accès
    if (!AuthHelper::peutAccederEspaceMedecin()) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Session expiree ou remplacee par une autre connexion.',
                'redirect' => 'medecin.php?action=connexion&session_remplacee=1',
            ]);
            exit;
        }
        header('Location: accueil.php');
        exit;
    }
    // Vérification du timeout de session
    $sessionOk = AuthHelper::verifierSession(null);
    if (!$sessionOk) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Session expiree ou remplacee par une autre connexion.',
                'redirect' => 'medecin.php?action=connexion&session_remplacee=1',
            ]);
            exit;
        }
        header('Location: medecin.php?action=connexion&session_remplacee=1');
        exit;
    }
}

// Configuration
error_reporting(E_ALL);
ini_set('display_errors', 1);

$allowedActions = [
    // Authentification
    'inscription', 'connexion', 'dashboard', 'deconnexion',
    // API
    'api_sous_services',
    // Actions AJAX consultations
    'get_consultations_data', 'get_stats_data',
    'demarrer_consultation_ajax', 'terminer_consultation_ajax',
    'marquer_absent_ajax', 'annuler_toutes_ajax',
    // Pause / Reprise
    'mettre_en_pause_ajax', 'reprendre_consultation_ajax',
    // Planning
    'get_planning_medecin',
    // Profil
    'get_profil_data', 'mettre_a_jour_profil', 'verifier_mdp',
    // Historique
    'get_historique',
    // Prochain RDV
    'get_creneaux_disponibles',
    'fixer_prochain_rdv_ajax',
    // Statistiques évolution (graphiques)
    'get_stats_evolution',
    // Temps d'attente moyen
    'get_temps_attente_evolution',
    'get_dashboard_medecin_data',
    // Langue
    'changer_langue',
];

if (!in_array($action, $allowedActions)) {
    http_response_code(404);
    die('Page introuvable.');
}

require_once __DIR__ . '/controllers/MedecinController.php';

$ctrl = new MedecinController();

switch ($action) {
    // Authentification
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
    
    // API
    case 'api_sous_services':
        $ctrl->apiSousServices();
        break;
    
    // Actions AJAX consultations
    case 'get_consultations_data':
        $ctrl->getConsultationsData();
        break;
    
    case 'get_stats_data':
        $ctrl->getStatsData();
        break;
    
    case 'demarrer_consultation_ajax':
        $ctrl->demarrerConsultationAjax();
        break;
    
    case 'terminer_consultation_ajax':
        $ctrl->terminerConsultationAjax();
        break;
    
    case 'marquer_absent_ajax':
        $ctrl->marquerAbsentAjax();
        break;

    case 'mettre_en_pause_ajax':
        $ctrl->mettreEnPauseAjax();
        break;

    case 'reprendre_consultation_ajax':
        $ctrl->reprendreConsultationAjax();
        break;
    
    case 'annuler_toutes_ajax':
        $ctrl->annulerToutesAjax();
        break;

    // Planning
    case 'get_planning_medecin':
        $ctrl->getPlanningMedecin();
        break;
    
    // Profil
    case 'get_profil_data':
        $ctrl->getProfilData();
        break;
    
    case 'mettre_a_jour_profil':
        $ctrl->mettreAJourProfil();
        break;

    case 'verifier_mdp':
        $ctrl->verifierMdp();
        break;
    
    // Historique
    case 'get_historique':
        $ctrl->getHistorique();
        break;

    // Prochain RDV
    case 'get_creneaux_disponibles':
        $ctrl->getCreneauxDisponibles();
        break;

    case 'fixer_prochain_rdv_ajax':
        $ctrl->fixerProchainRdvAjax();
        break;

    case 'get_stats_evolution':
        $ctrl->getStatsEvolution();
        break;

    case 'get_temps_attente_evolution':
        $ctrl->getTempsAttenteEvolution();
        break;

    case 'get_dashboard_medecin_data':
        $ctrl->getDashboardMedecinData();
        break;

    case 'changer_langue':
        $ctrl->changerLangue();
        break;

    default:
        $ctrl->afficherDashboard();
        break;
}
