<?php
/**
 * service.php — Point d'entrée espace administration (directeur)
 * Gestion des sous-services, horaires, médecins, jours de travail
 * 
 * ACCÈS : UNIQUEMENT l'administrateur (directeur de l'hôpital)
 */

require_once __DIR__ . '/helpers/AuthHelper.php';

// Vérification des droits d'accès (uniquement admin)
if (!AuthHelper::peutAccederEspaceAdmin()) {
    header('Location: accueil.php');
    exit;
}

// Vérification du timeout de session
AuthHelper::verifierSession('service.php?action=sous_services');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Actions autorisées en mode mono-service
$allowedActions = [
    'sous_services',        // Page principale de gestion des sous-services
    'creer_ss',             // Création d'un sous-service
    'modifier_ss',          // Modification d'un sous-service
    'basculer_statut_ss',   // Bascule statut d'un sous-service
    'supprimer_ss',         // Suppression d'un sous-service
    'horaires',             // Gestion des horaires du service
    'maj_horaires',         // Mise à jour des horaires
    'medecins',             // Liste des médecins
    'jours_travail',        // Configuration jours de travail d'un médecin
    'traiter_jours_travail' // Sauvegarde jours de travail
];

$action = $_GET['action'] ?? 'sous_services';

// Vérification de l'action demandée
if (!in_array($action, $allowedActions)) {
    header('Location: service.php?action=sous_services');
    exit;
}

// Inclusion du contrôleur
require_once __DIR__ . '/controllers/ServiceController.php';

$ctrl = new ServiceController();

switch ($action) {
    case 'sous_services':
        $ctrl->gestionSousServices();
        break;
    case 'creer_ss':
        $ctrl->creerSousService();
        break;
    case 'modifier_ss':
        $ctrl->modifierSousService();
        break;
    case 'basculer_statut_ss':
        $ctrl->basculerStatutSousService();
        break;
    case 'supprimer_ss':
        $ctrl->supprimerSousService();
        break;
    case 'horaires':
        $ctrl->gestionHoraires();
        break;
    case 'maj_horaires':
        $ctrl->mettreAJourHoraires();
        break;
    case 'medecins':
        $ctrl->listeMedecins();
        break;
    case 'jours_travail':
        $ctrl->configurerJoursTravail();
        break;
    case 'traiter_jours_travail':
        $ctrl->traiterJoursTravail();
        break;
    default:
        $ctrl->gestionSousServices();
        break;
}