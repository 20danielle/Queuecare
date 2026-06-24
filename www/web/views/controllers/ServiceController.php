<?php
/**
 * controllers/ServiceController.php
 * Contrôleur MVC — Gestion des Services (version mono-service)
 */

if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../models/ServiceModel.php';

class ServiceController
{
    private ServiceModel $model;

    public function __construct()
    {
        $this->model = new ServiceModel();
    }

    /* ═══════════════════════════════════════════════════════════
       HORAIRES DU SERVICE UNIQUE
    ═══════════════════════════════════════════════════════════ */

    /**
     * Affiche la page de gestion des horaires (service unique id=1)
     */
    public function afficherHoraires(): void
    {
        // Récupérer le service unique (id=1)
        $service = $this->model->getServiceById(1);
        
        // Créer le service par défaut s'il n'existe pas
        if (!$service) {
            $this->model->creerServiceParDefaut();
            $service = $this->model->getServiceById(1);
        }
        
        // S'assurer que $service n'est pas null
        if (!$service) {
            $service = [
                'id' => 1,
                'nom' => 'CMA Tyo de Baleng',
                'adresse' => 'PMI, entrée école normale',
                'horaires_ouverture' => '08:00:00',
                'horaires_fermeture' => '18:00:00',
                'pause_debut' => null,
                'pause_fin' => null,
                'jours_fermeture' => ''
            ];
        }
        
        $joursSemaine = $this->model->getJoursSemaine();
        $joursFermeture = !empty($service['jours_fermeture']) ? explode(',', $service['jours_fermeture']) : [];
        $joursFermeture = array_map('intval', $joursFermeture);
        
        $messageAction = $_SESSION['message_action'] ?? '';
        $typeMessage   = $_SESSION['type_message']   ?? 'success';
        unset($_SESSION['message_action'], $_SESSION['type_message']);
        
        require __DIR__ . '/../views/service/horaires.php';
    }

    /**
     * Met à jour les horaires du service unique
     */
    public function mettreAJourHoraires(): void
    {
        $horairesOuverture = trim($_POST['horaires_ouverture'] ?? '08:00');
        $horairesFermeture = trim($_POST['horaires_fermeture'] ?? '18:00');
        $pauseDebut = !empty($_POST['pause_debut']) ? trim($_POST['pause_debut']) : null;
        $pauseFin = !empty($_POST['pause_fin']) ? trim($_POST['pause_fin']) : null;
        $joursFermeture = $_POST['jours_fermeture'] ?? [];
        $joursFermetureStr = implode(',', $joursFermeture);

        // Validation basique
        $errors = [];
        if ($horairesOuverture && $horairesFermeture && $horairesOuverture >= $horairesFermeture) {
            $errors[] = "L'heure de fermeture doit être après l'heure d'ouverture.";
        }
        if ($pauseDebut && $pauseFin && $pauseDebut >= $pauseFin) {
            $errors[] = "La fin de la pause doit être après le début de la pause.";
        }

        if (!empty($errors)) {
            $_SESSION['message_action'] = implode(', ', $errors);
            $_SESSION['type_message'] = 'error';
            header('Location: service.php?action=horaires');
            exit;
        }

        $ok = $this->model->updateHoraires(1, [
            'horaires_ouverture' => $horairesOuverture . ':00',
            'horaires_fermeture' => $horairesFermeture . ':00',
            'pause_debut'        => $pauseDebut ? $pauseDebut . ':00' : null,
            'pause_fin'          => $pauseFin ? $pauseFin . ':00' : null,
            'jours_fermeture'    => $joursFermetureStr,
        ]);

        if ($ok) {
            $_SESSION['message_action'] = "Horaires mis à jour avec succès.";
            $_SESSION['type_message']   = 'success';
        } else {
            $_SESSION['message_action'] = "Erreur lors de la mise à jour des horaires.";
            $_SESSION['type_message']   = 'error';
        }
        
        header('Location: service.php?action=horaires');
        exit;
    }

    /* ═══════════════════════════════════════════════════════════
       MÉDECINS ET JOURS DE TRAVAIL
    ═══════════════════════════════════════════════════════════ */

    /**
     * Affiche la liste des médecins
     */
    public function afficherMedecins(): void
    {
        $medecins = $this->model->getAllMedecins();
        $joursSemaine = $this->model->getJoursSemaine();
        
        foreach ($medecins as &$med) {
            $med['jours_travail'] = $this->model->getJoursTravailMedecin($med['id']);
        }
        
        $messageAction = $_SESSION['message_action'] ?? '';
        $typeMessage   = $_SESSION['type_message']   ?? 'success';
        unset($_SESSION['message_action'], $_SESSION['type_message']);
        
        require __DIR__ . '/../views/service/medecins.php';
    }

    /**
     * Affiche le formulaire de configuration des jours de travail
     */
    public function afficherJoursTravail(): void
    {
        $medecinId = (int)($_GET['medecin_id'] ?? 0);
        
        if ($medecinId === 0) {
            header('Location: service.php?action=medecins');
            exit;
        }
        
        $medecin = $this->model->getMedecinParId($medecinId);
        if (!$medecin) {
            header('Location: service.php?action=medecins');
            exit;
        }
        
        $joursSemaine = $this->model->getJoursSemaine();
        $joursTravail = $this->model->getJoursTravailMedecin($medecinId);
        
        require __DIR__ . '/../views/service/jours_travail.php';
    }

    /**
     * Sauvegarde les jours de travail d'un médecin
     */
    public function traiterJoursTravail(): void
    {
        $medecinId = (int)($_POST['medecin_id'] ?? 0);
        
        if ($medecinId === 0) {
            header('Location: service.php?action=medecins');
            exit;
        }
        
        $jours = $_POST['jours'] ?? [];
        $this->model->sauvegarderJoursTravailMedecin($medecinId, $jours);
        
        $_SESSION['message_action'] = 'Jours de travail du médecin mis à jour.';
        $_SESSION['type_message']   = 'success';
        header('Location: service.php?action=medecins');
        exit;
    }

    /* ═══════════════════════════════════════════════════════════
       SOUS-SERVICES
    ═══════════════════════════════════════════════════════════ */

    /**
     * Affiche la page de gestion des sous-services
     */
    public function afficherSousServices(): void
    {
        $sousServices = $this->model->getAllSousServices();
        $messageAction = $_SESSION['message_action'] ?? '';
        $typeMessage   = $_SESSION['type_message']   ?? 'success';
        unset($_SESSION['message_action'], $_SESSION['type_message']);
        
        require __DIR__ . '/../views/service/sous_services.php';
    }

    /**
     * Crée un nouveau sous-service
     */
    public function traiterCreerSousService(): void
    {
        $serviceId = 1;
        $nom = trim($_POST['ss_nom'] ?? '');
        $description = trim($_POST['ss_description'] ?? '');
        $duree = (int)($_POST['ss_duree'] ?? 1800);
        $capacite = (int)($_POST['ss_capacite'] ?? 10);

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if (mb_strlen($nom) < 2) {
            $this->respondWithError('Le nom doit contenir au moins 2 caractères.', $isAjax);
            return;
        }

        if ($this->model->nomSsExiste($nom, $serviceId)) {
            $this->respondWithError("Un sous-service « {$nom} » existe déjà.", $isAjax);
            return;
        }

        $ok = $this->model->creerSousService([
            'service_id'      => $serviceId,
            'nom'             => $nom,
            'description'     => $description,
            'duree_rdv_defaut'=> $duree,
            'capacite_horaire'=> $capacite,
        ]);

        if ($isAjax) {
            if ($ok) {
                $newSs = $this->model->getSousServiceParNom($nom, $serviceId);
                echo json_encode([
                    'success' => true,
                    'message' => "Sous-service « {$nom} » créé.",
                    'sous_service' => $newSs
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de la création.']);
            }
            exit;
        }

        $_SESSION['message_action'] = $ok ? "Sous-service « {$nom} » créé." : 'Erreur lors de la création.';
        $_SESSION['type_message'] = $ok ? 'success' : 'error';
        header('Location: service.php?action=sous_services');
        exit;
    }

    /**
     * Modifie un sous-service
     */
    public function traiterModifierSousService(): void
    {
        $ssId = (int)($_POST['ss_id'] ?? 0);
        $nom = trim($_POST['ss_nom'] ?? '');
        $description = trim($_POST['ss_description'] ?? '');
        $duree = (int)($_POST['ss_duree'] ?? 1800);
        $capacite = (int)($_POST['ss_capacite'] ?? 10);
        $statut = trim($_POST['ss_statut'] ?? 'actif');

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($ssId === 0 || mb_strlen($nom) < 2) {
            $this->respondWithError('Données invalides.', $isAjax);
            return;
        }

        if ($this->model->nomSsExiste($nom, 1, $ssId)) {
            $this->respondWithError("Un autre sous-service « {$nom} » existe déjà.", $isAjax);
            return;
        }

        $ok = $this->model->modifierSousService($ssId, [
            'nom'             => $nom,
            'description'     => $description,
            'duree_rdv_defaut'=> $duree,
            'capacite_horaire'=> $capacite,
            'statut'          => $statut
        ]);

        if ($isAjax) {
            echo json_encode([
                'success' => $ok,
                'message' => $ok ? "Sous-service modifié." : "Erreur lors de la modification."
            ]);
            exit;
        }

        $_SESSION['message_action'] = $ok ? "Sous-service modifié." : 'Erreur lors de la modification.';
        $_SESSION['type_message'] = $ok ? 'success' : 'error';
        header('Location: service.php?action=sous_services');
        exit;
    }

    /**
     * Bascule le statut d'un sous-service
     */
    public function basculerStatutSousService(): void
    {
        $ssId = (int)($_GET['ss_id'] ?? $_POST['ss_id'] ?? 0);
        
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($ssId > 0) {
            $ok = $this->model->basculerStatutSs($ssId);
            if ($isAjax) {
                $nouveauStatut = $this->model->getStatutSs($ssId);
                echo json_encode([
                    'success' => $ok,
                    'statut' => $nouveauStatut,
                    'message' => 'Statut mis à jour.'
                ]);
                exit;
            }
            $_SESSION['message_action'] = 'Statut du sous-service mis à jour.';
            $_SESSION['type_message']   = 'info';
        }
        header('Location: service.php?action=sous_services');
        exit;
    }

    /**
     * Supprime un sous-service
     */
    public function supprimerSousService(): void
    {
        $ssId = (int)($_GET['ss_id'] ?? $_POST['ss_id'] ?? 0);
        
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                  strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        if ($ssId > 0) {
            $res = $this->model->supprimerSousService($ssId);
            if ($isAjax) {
                echo json_encode([
                    'success' => $res['ok'],
                    'message' => $res['msg']
                ]);
                exit;
            }
            $_SESSION['message_action'] = $res['msg'];
            $_SESSION['type_message']   = $res['ok'] ? 'success' : 'error';
        }
        header('Location: service.php?action=sous_services');
        exit;
    }

    private function respondWithError(string $message, bool $isAjax): void
    {
        if ($isAjax) {
            echo json_encode(['success' => false, 'message' => $message]);
            exit;
        }
        $_SESSION['message_action'] = $message;
        $_SESSION['type_message'] = 'error';
        header('Location: service.php?action=sous_services');
        exit;
    }
}

/* ═══════════════════════════════════════════════════════════
   DISPATCHER
═════════════════════════════════════════════════════════════ */

$action = $_GET['action'] ?? 'sous_services';

$allowed = [
    'sous_services', 'creer_ss', 'modifier_ss', 'basculer_statut_ss', 'supprimer_ss',
    'horaires', 'maj_horaires', 'medecins', 'jours_travail', 'traiter_jours_travail'
];

if (!in_array($action, $allowed)) {
    header('Location: service.php?action=sous_services');
    exit;
}

$ctrl = new ServiceController();

switch ($action) {
    case 'sous_services':
        $ctrl->afficherSousServices();
        break;
    case 'creer_ss':
        $ctrl->traiterCreerSousService();
        break;
    case 'modifier_ss':
        $ctrl->traiterModifierSousService();
        break;
    case 'basculer_statut_ss':
        $ctrl->basculerStatutSousService();
        break;
    case 'supprimer_ss':
        $ctrl->supprimerSousService();
        break;
    case 'horaires':
        $ctrl->afficherHoraires();
        break;
    case 'maj_horaires':
        $ctrl->mettreAJourHoraires();
        break;
    case 'medecins':
        $ctrl->afficherMedecins();
        break;
    case 'jours_travail':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ctrl->traiterJoursTravail();
        } else {
            $ctrl->afficherJoursTravail();
        }
        break;
    case 'traiter_jours_travail':
        $ctrl->traiterJoursTravail();
        break;
    default:
        $ctrl->afficherSousServices();
        break;
}