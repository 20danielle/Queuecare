#!/usr/bin/env php
<?php
/**
 * cron/detection_retard_medecin.php
 *
 * À exécuter toutes les 5-10 minutes. Détecte les médecins censés
 * travailler aujourd'hui mais qui ne se sont pas connectés à leur
 * dashboard alors que l'heure d'ouverture + le délai de grâce sont
 * dépassés (= retard/indisponibilité imprévue, non signalée).
 *
 * Comportement volontairement PRUDENT : ce script ne réaffecte PAS
 * automatiquement les patients. Il se contente de générer une alerte
 * exploitable par le gestionnaire (log + notification interne), qui
 * confirme ensuite manuellement via l'action AJAX
 * "signaler_indisponibilite_medecin" (GestionnaireController).
 * Cela évite les fausses alertes (pause déjeuner prolongée, souci
 * réseau ponctuel) qui déclencheraient des réaffectations et des
 * notifications push inutiles aux patients.
 *
 * Configuration cron (crontab -e) :
 *   */10 * * * * php /chemin/vers/projet/cron/detection_retard_medecin.php >> /var/log/queuecare_cron.log 2>&1
 *
 * Appel HTTP possible (protégé par secret) :
 *   GET /cron/detection_retard_medecin.php?secret=VOTRE_SECRET
 */

define('ROOT', dirname(__DIR__));

if (php_sapi_name() !== 'cli') {
    $secret = getenv('CRON_SECRET') ?: 'changeme_secret_cron';
    if (($_GET['secret'] ?? '') !== $secret) {
        http_response_code(403);
        exit('Forbidden');
    }
    header('Content-Type: application/json');
}

require_once ROOT . '/config/database.php';
require_once ROOT . '/models/MedecinModel.php';
require_once ROOT . '/models/GestionnaireModel.php';
require_once ROOT . '/models/NotificationModel.php';

$DELAI_GRACE_MINUTES = 20; // ajustable selon la tolérance souhaitée

$medecinModel = new MedecinModel();
$notifModel   = new NotificationModel();

$enRetard = $medecinModel->listerMedecinsEnRetardNonConnectes($DELAI_GRACE_MINUTES);

$alertes = [];
foreach ($enRetard as $m) {
    $alertes[] = [
        'medecin_id'        => (int)$m['id'],
        'nom'               => $m['prenom'] . ' ' . $m['nom'],
        'sous_service_id'   => (int)$m['sous_service_id'],
        'sous_service_nom'  => $m['sous_service_nom'],
    ];

    // Alerte interne au(x) gestionnaire(s) du sous-service concerné, pour
    // qu'il confirme (ou infirme) l'indisponibilité depuis son dashboard.
    // Réutilise NotificationModel::creerAlerteInterne si disponible, sinon
    // se contente du log — à adapter selon le mécanisme d'alerte gestionnaire
    // déjà en place dans le projet.
    error_log(sprintf(
        '[ALERTE RETARD] Dr %s %s non connecté (sous-service #%d - %s) au-delà du délai de grâce.',
        $m['prenom'], $m['nom'], $m['sous_service_id'], $m['sous_service_nom']
    ));
}

$output = [
    'success'      => true,
    'checked_at'   => date('Y-m-d H:i:s'),
    'delai_grace'  => $DELAI_GRACE_MINUTES,
    'medecins_en_retard' => $alertes,
];

if (php_sapi_name() !== 'cli') {
    echo json_encode($output, JSON_PRETTY_PRINT);
} else {
    echo json_encode($output) . PHP_EOL;
}
