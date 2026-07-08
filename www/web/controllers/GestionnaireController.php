<?php
/**
 * controllers/GestionnaireController.php
 * Contrôleur pour l'espace Gestionnaire - Avec table utilisateurs
 */

require_once __DIR__ . '/../models/GestionnaireModel.php';
require_once __DIR__ . '/../models/UtilisateurModel.php';
require_once __DIR__ . '/../models/MedecinModel.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../helpers/QueueNotificationService.php';
require_once __DIR__ . '/../helpers/LangHelper.php';

class GestionnaireController
{
    private GestionnaireModel $model;
    private UtilisateurModel $utilisateurModel;
    private MedecinModel $medecinModel;
    private ?string $initError = null;

    public function __construct()
    {
        try {
            $this->model = new GestionnaireModel();
            $this->utilisateurModel = new UtilisateurModel();
            $this->medecinModel = new MedecinModel();
        } catch (\Throwable $e) {
            error_log('[Gestionnaire] Erreur initialisation: ' . $e->getMessage());
            $this->initError = 'Erreur d\'initialisation (base de données ?) : ' . $e->getMessage();
        }
    }

    /**
     * Si l'initialisation a échoué (ex: connexion BDD), répond proprement
     * en JSON pour les actions AJAX au lieu de laisser une 500 brute.
     */
    private function checkInit(): bool
    {
        if ($this->initError !== null) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $this->initError]);
            exit;
        }
        return true;
    }

    /* ════════════════════════════════════════════════════════
       INSCRIPTION
    ════════════════════════════════════════════════════════ */

    public function afficherInscription(): void
    {
        if (AuthHelper::estConnecte()) {
            header('Location: gestionnaire.php?action=dashboard');
            exit;
        }
        
        $erreurs      = [];
        $anciens      = [];
        $sousServices = $this->model->getSousServices();
        require __DIR__ . '/../views/gestionnaire/inscription.php';
    }

    public function traiterInscription(): void
    {
        $erreurs      = [];
        $sousServices = $this->model->getSousServices();

        $nom           = trim($_POST['nom']               ?? '');
        $telephone     = trim($_POST['telephone']         ?? '');
        $email         = trim($_POST['email']             ?? '');
        $password      = $_POST['password']               ?? '';
        $confirm       = $_POST['confirm']                ?? '';
        $sousServiceId = (int)($_POST['sous_service_id']  ?? 0);
        $langue        = in_array($_POST['langue'] ?? '', ['fr','en']) ? $_POST['langue'] : 'fr';

        $anciens = [
            'nom'             => $nom,
            'telephone'       => $telephone,
            'email'           => $email,
            'sous_service_id' => $sousServiceId,
            'langue'          => $langue,
        ];

        if (mb_strlen($nom) < 2) {
            $erreurs['nom'] = 'Le nom doit contenir au moins 2 caractères.';
        }

        if (!preg_match('/^\+?[0-9]{8,19}$/', $telephone)) {
            $erreurs['telephone'] = 'Numéro invalide (chiffres et + uniquement).';
        } elseif ($this->model->telephoneExiste($telephone)) {
            $erreurs['telephone'] = 'Ce numéro est déjà utilisé.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreurs['email'] = 'Adresse email invalide.';
        } elseif ($this->model->emailExiste($email)) {
            $erreurs['email'] = 'Cette adresse email est déjà utilisée.';
        } elseif ($this->utilisateurModel->emailExiste($email)) {
            $erreurs['email'] = 'Cette adresse email est déjà utilisée dans le système.';
        }

        if ($sousServiceId === 0) {
            $erreurs['sous_service_id'] = 'Veuillez sélectionner un sous-service.';
        }

        if (strlen($password) < 8) {
            $erreurs['password'] = 'Minimum 8 caractères.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $erreurs['password'] = 'Au moins une majuscule requise.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $erreurs['password'] = 'Au moins un chiffre requis.';
        }

        if ($password !== $confirm) {
            $erreurs['confirm'] = 'Les mots de passe ne correspondent pas.';
        }

        if (!empty($erreurs)) {
            require __DIR__ . '/../views/gestionnaire/inscription.php';
            return;
        }

        // 🔐 HASHER LE MOT DE PASSE AVANT STOCKAGE
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // 1. Créer le gestionnaire avec mot de passe hashé
        $gestionnaireId = $this->model->creer([
            'nom'             => $nom,
            'telephone'       => $telephone,
            'email'           => $email,
            'password'        => $hashedPassword,  // ← Mot de passe hashé
            'sous_service_id' => $sousServiceId,
            'langue'          => $langue,
        ]);

        if (!$gestionnaireId) {
            $erreurs['global'] = 'Erreur lors de la création du compte gestionnaire.';
            require __DIR__ . '/../views/gestionnaire/inscription.php';
            return;
        }

        // 2. Créer le compte utilisateur associé avec mot de passe hashé
        $userId = $this->utilisateurModel->creer([
            'email' => $email,
            'password' => $hashedPassword,  // ← Mot de passe déjà hashé
            'role' => 'gestionnaire',
            'nom' => $nom,
            'gestionnaire_id' => $gestionnaireId
        ]);

        if (!$userId) {
            $erreurs['global'] = 'Erreur lors de la création du compte utilisateur.';
            require __DIR__ . '/../views/gestionnaire/inscription.php';
            return;
        }

        // 3. Connecter automatiquement
        AuthHelper::connecterGestionnaire($gestionnaireId, $nom, $email);
        $_SESSION['user_id'] = $userId;

        // Récupérer le sous-service
        $sousService = $this->model->getSousServiceByGestionnaire($gestionnaireId);
        if ($sousService) {
            $_SESSION['sous_service_id'] = $sousService['id'];
        }

        header('Location: gestionnaire.php?action=dashboard');
        exit;
    }

    /* ════════════════════════════════════════════════════════
       CONNEXION
    ════════════════════════════════════════════════════════ */

    public function afficherConnexion(): void
    {
        if (AuthHelper::estConnecte()) {
            header('Location: gestionnaire.php?action=dashboard');
            exit;
        }
        
        $erreurs      = [];
        $ancien_email = '';
        require __DIR__ . '/../views/gestionnaire/connexion.php';
    }

    public function traiterConnexion(): void
    {
        $erreurs      = [];
        $ancien_email = trim($_POST['email']    ?? '');
        $password     = $_POST['password']      ?? '';

        if (empty($ancien_email) || empty($password)) {
            $erreurs['global'] = 'Veuillez remplir tous les champs.';
            require __DIR__ . '/../views/gestionnaire/connexion.php';
            return;
        }

        // Authentifier via la table utilisateurs
        $user = $this->utilisateurModel->authentifier($ancien_email, $password);

        if (!$user || $user['role'] !== 'gestionnaire') {
            $erreurs['global'] = 'Email ou mot de passe incorrect.';
            require __DIR__ . '/../views/gestionnaire/connexion.php';
            return;
        }

        // Vérifier que le gestionnaire existe
        $gestionnaire = $this->model->trouverParId($user['gestionnaire_id']);
        if (!$gestionnaire) {
            $erreurs['global'] = 'Compte gestionnaire introuvable.';
            require __DIR__ . '/../views/gestionnaire/connexion.php';
            return;
        }

        // Mettre à jour la dernière connexion
        $this->utilisateurModel->updateDerniereConnexion($user['id']);

        // Connecter — initSession() démarre session_start()
        AuthHelper::connecterGestionnaire($user['gestionnaire_id'], $user['nom'], $user['email']);
        $_SESSION['user_id'] = $user['id'];

        // Récupérer le sous-service
        $sousService = $this->model->getSousServiceByGestionnaire($user['gestionnaire_id']);
        if ($sousService) {
            $_SESSION['sous_service_id'] = $sousService['id'];
        }

        // Restaurer la langue préférée du gestionnaire
        $gestionnaireData = $this->model->trouverParId((int)$user['gestionnaire_id']);
        LangHelper::setLang($gestionnaireData['langue'] ?? 'fr');

        header('Location: gestionnaire.php?action=dashboard');
        exit;
    }

    /* ════════════════════════════════════════════════════════
       DASHBOARD
    ════════════════════════════════════════════════════════ */

    public function afficherDashboard(): void
    {
        if (!AuthHelper::estGestionnaire()) {
            header('Location: gestionnaire.php?action=connexion');
            exit;
        }

        $gestionnaireId  = (int)$_SESSION['gestionnaire_id'];
        $gestionnaireNom = $_SESSION['user_nom'];

        $sousService = $this->model->getSousServiceGestionnaire($gestionnaireId);
        if (!$sousService) {
            die('Aucun sous-service affecté à ce compte. Contactez l\'administrateur.');
        }

        $ssId          = (int)$sousService['id'];

        // Répare les consultations sans médecin assigné (medecin_id NULL ou 0)
        // dès le chargement de la page, pas seulement au rafraîchissement AJAX.
        $this->model->affecterConsultationsSansMedecinDuJour($ssId);

        $stats         = $this->model->statsJour($ssId);
        $file          = $this->model->fileAttente($ssId);
        $consultations = $this->model->consultationsJour($ssId);
        $urgences      = $this->model->urgencesOuvertes($ssId);
        $messageAction = '';
        $typeMessage   = 'success';
        $medecins      = $this->model->getMedecinsDisponibles($ssId);
        $tousMedecins  = $this->model->getTousMedecins($ssId);
        $horairesJour  = $this->model->getHorairesJour($ssId);
        $serviceHoraires = $this->model->getServiceHoraires($ssId);

        $_SESSION['sous_service_id'] = $ssId;

        // Endpoint AJAX : chercher un patient par téléphone
        if (isset($_GET['api']) && $_GET['api'] === 'patient') {
            header('Content-Type: application/json; charset=utf-8');
            $tel    = trim($_GET['tel'] ?? '');
            $result = $tel ? $this->model->chercherPatientParTel($tel) : false;
            echo json_encode($result ?: null);
            exit;
        }

        $openQR = isset($_GET['open_qr']) ? true : false;
        require __DIR__ . '/../views/gestionnaire/dashboard.php';
    }

    /* ════════════════════════════════════════════════════════
       DÉCONNEXION
    ════════════════════════════════════════════════════════ */

    public function deconnecter(): void
    {
        session_unset();
        session_destroy();
        header('Location: accueil.php');
        exit;
    }
    
    // ═══════════════════════════════════════════════════════════════════════════════
    // MÉTHODES AJAX (traiterActionAjax, getDashboardData, getProfilData, etc.)
    // ═══════════════════════════════════════════════════════════════════════════════
    
    public function traiterActionAjax(): void
    {
        header('Content-Type: application/json');
        
        if (!AuthHelper::estGestionnaire()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }
        
        $action = $_POST['action'] ?? '';
        $ssId = $_SESSION['sous_service_id'] ?? null;
        
        if (!$ssId) {
            $gestionnaireId = (int)$_SESSION['gestionnaire_id'];
            $sousService = $this->model->getSousServiceGestionnaire($gestionnaireId);
            $ssId = $sousService['id'] ?? null;
            $_SESSION['sous_service_id'] = $ssId;
        }
        
        if (!$ssId) {
            echo json_encode(['success' => false, 'message' => 'Sous-service non trouvé']);
            exit;
        }
        
        // Action: Mise à jour du statut
        if ($action === 'maj_statut') {
            $cId    = (int)($_POST['consultation_id'] ?? 0);
            $statut = $_POST['statut'] ?? '';
            $autorises = ['traite', 'annule', 'absent'];
            
            if ($cId > 0 && in_array($statut, $autorises, true)) {
                try {
                    $ok = $this->model->majStatutConsultation($cId, $statut);
                } catch (\RuntimeException $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }

                if (!$ok) {
                    echo json_encode(['success' => false, 'message' => 'Consultation introuvable.']);
                    exit;
                }

                // Notifications push selon le nouveau statut
                try {
                    $notifSvc = new QueueNotificationService();
                    if ($statut === 'traite') {
                        $notifSvc->onConsultationTerminee($cId, $ssId);
                    } elseif ($statut === 'absent') {
                        $notifSvc->onPatientAbsent($cId, $ssId);
                    } elseif ($statut === 'annule') {
                        $motif = trim($_POST['motif'] ?? '');
                        $notifSvc->onConsultationAnnulee($cId, $ssId, $motif);
                    }
                } catch (\Throwable $e) {
                    error_log('[FCM] maj_statut: ' . $e->getMessage());
                }

                echo json_encode(['success' => true, 'message' => 'Statut mis à jour.']);
                exit;
            }
            echo json_encode(['success' => false, 'message' => 'Action invalide.']);
            exit;
        }

        // Action: Signaler un retard/indisponibilité imprévue du médecin.
        // Le gestionnaire confirme manuellement (recommandé) après avoir été
        // alerté — automatiquement ou en constatant que le médecin n'est
        // pas connecté — puis les patients déjà en attente sont réaffectés
        // à un autre médecin disponible ET connecté du même sous-service,
        // avec notification push (arrière-plan) à chacun.
        if ($action === 'signaler_indisponibilite_medecin') {
            $medecinId = (int)($_POST['medecin_id'] ?? 0);

            if ($medecinId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Médecin invalide.']);
                exit;
            }

            $resultat = $this->medecinModel->reaffecterFileVersMedecinsDisponibles($medecinId, (int)$ssId);

            try {
                $notifSvc = new QueueNotificationService();
                $notifSvc->onReaffectationMedecin($resultat);
            } catch (\Throwable $e) {
                error_log('[FCM] signaler_indisponibilite_medecin: ' . $e->getMessage());
            }

            echo json_encode([
                'success'       => true,
                'reaffectees'   => count($resultat['reaffectees']),
                'sans_solution' => count($resultat['sans_solution']),
                'message'       => count($resultat['reaffectees']) . ' patient(s) réaffecté(s), '
                                  . count($resultat['sans_solution']) . ' en attente d\'une solution.',
            ]);
            exit;
        }

        // Action: Bouton "Urgence" du dashboard gestionnaire — le
        // gestionnaire signale qu'un médecin de son sous-service est
        // appelé en urgence et doit quitter.
        // - Si le médecin a une consultation en cours/en pause : la
        //   bascule "indisponible" + déconnexion sont différées jusqu'à la
        //   fin de cette consultation (gérées médecin.php côté médecin) ;
        //   la file d'attente est immédiatement avertie par notification
        //   push que le médecin est indisponible (elle ne bouge donc pas
        //   pour l'instant).
        // - Sinon : bascule et déconnexion immédiates, et la file du
        //   médecin (pas encore prise en charge) est aussitôt répartie de
        //   façon égale entre les autres médecins disponibles du
        //   sous-service (s'il y en a).
        // Dans les deux cas, si le médecin revient et que sa file (ou le
        // reste) n'a pas encore été traitée, elle lui revient
        // automatiquement dès sa reconnexion (MedecinController::traiterConnexion).
        if ($action === 'declencher_urgence_medecin') {
            $medecinId = (int)($_POST['medecin_id'] ?? 0);

            if ($medecinId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Médecin invalide.']);
                exit;
            }

            // Le médecin ciblé doit bien appartenir au sous-service du gestionnaire.
            $affectation = $this->medecinModel->getSousServiceMedecin($medecinId);
            if (!$affectation || (int)$affectation['ss_id'] !== (int)$ssId) {
                echo json_encode(['success' => false, 'message' => 'Ce médecin n\'appartient pas à votre sous-service.']);
                exit;
            }

            $gestionnaireId = (int)$_SESSION['gestionnaire_id'];
            $resultat = $this->medecinModel->declencherUrgenceParGestionnaire($medecinId, $gestionnaireId);

            // Avertir immédiatement la file d'attente du médecin : elle ne
            // bouge pas pour le moment, qu'une consultation soit en cours
            // ou non.
            try {
                $notifSvc = new QueueNotificationService();
                $notifSvc->onUrgenceOuReport(
                    $ssId,
                    "Le médecin en charge de votre consultation a été appelé en urgence et est momentanément indisponible. Merci de patienter, vous serez tenu(e) informé(e)."
                );
            } catch (\Throwable $e) {
                error_log('[FCM] declencher_urgence_medecin (file avertie): ' . $e->getMessage());
            }

            if ($resultat['immediate']) {
                // Aucune consultation en cours : bascule immédiate, on
                // répartit tout de suite la file non prise en charge entre
                // les autres médecins disponibles du sous-service.
                $reaffectation = $this->medecinModel->reaffecterFileVersMedecinsDisponibles($medecinId, (int)$ssId);
                try {
                    $notifSvc = new QueueNotificationService();
                    $notifSvc->onReaffectationMedecin($reaffectation);
                } catch (\Throwable $e) {
                    error_log('[FCM] declencher_urgence_medecin (réaffectation): ' . $e->getMessage());
                }

                echo json_encode([
                    'success'       => true,
                    'immediate'     => true,
                    'reaffectees'   => count($reaffectation['reaffectees']),
                    'sans_solution' => count($reaffectation['sans_solution']),
                    'message'       => 'Médecin placé en indisponibilité et déconnecté. '
                                      . count($reaffectation['reaffectees']) . ' patient(s) réaffecté(s), '
                                      . count($reaffectation['sans_solution']) . ' en attente d\'une solution.',
                ]);
                exit;
            }

            echo json_encode([
                'success'   => true,
                'immediate' => false,
                'message'   => 'Urgence enregistrée : le médecin sera automatiquement placé en indisponibilité et déconnecté dès la fin de sa consultation en cours. Sa file sera alors réaffectée si besoin.',
            ]);
            exit;
        }

        // Action: Signaler au médecin que le patient est revenu d'examen
        // (cloche) — ne change PAS le statut, le médecin reprend lui-même.
        if ($action === 'signaler_retour_patient') {
            $cId = (int)($_POST['consultation_id'] ?? 0);
            if ($cId > 0 && $this->model->signalerRetourPatient($cId)) {
                // Notifier le patient qu'il peut revenir
                try {
                    $notifSvc = new QueueNotificationService();
                    $notifSvc->onRetourExamen($cId);
                } catch (\Throwable $e) {
                    error_log('[FCM] retour_examen: ' . $e->getMessage());
                }
                echo json_encode(['success' => true, 'message' => 'Le médecin a été averti du retour du patient.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Impossible de signaler ce retour.']);
            }
            exit;
        }
        
        // Action: Consultation manuelle - sans sélection de médecin
        if ($action === 'consultation_manuelle') {
            $patNom = trim($_POST['patient_nom'] ?? '');
            $patPrenom = trim($_POST['patient_prenom'] ?? '');
            $patTel = trim($_POST['patient_telephone'] ?? '');
            $patEmail = trim($_POST['patient_email'] ?? '');
            $statut = trim($_POST['statut'] ?? 'en_attente');
            $motif = trim($_POST['motif'] ?? '');

            $errC = [];
            if (mb_strlen($patNom) < 2) $errC[] = 'Nom requis.';
            if (mb_strlen($patPrenom) < 2) $errC[] = 'Prénom requis.';
            if (!preg_match('/^\+?[0-9]{8,20}$/', $patTel)) $errC[] = 'Téléphone invalide.';

            if (empty($errC)) {
                try {
                    $patientId = $this->model->rechercherOuCreerPatient([
                        'nom' => $patNom, 
                        'prenom' => $patPrenom,
                        'telephone' => $patTel, 
                        'email' => $patEmail
                    ]);

                    $consultId = $this->model->enregistrerConsultationManuelle([
                        'patient_id' => $patientId,
                        'sous_service_id' => $ssId,
                        'mode_prise' => 'MANUEL',
                        'statut' => $statut,
                        'motif' => $motif
                    ]);

                    if ($consultId > 0) {
                        try {
                            $notifSvc = new QueueNotificationService();
                            $notifSvc->onNouvelleConsultation($consultId);
                        } catch (\Throwable $e) {
                            error_log('[FCM] consultation_manuelle: ' . $e->getMessage());
                        }
                        echo json_encode(['success' => true, 'message' => "Consultation enregistrée — {$patPrenom} {$patNom}."]);
                        exit;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement. Vérifiez les données.']);
                        exit;
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Erreur : ' . $e->getMessage()]);
                    exit;
                }
            } else {
                echo json_encode(['success' => false, 'message' => implode(' | ', $errC)]);
                exit;
            }
        }
        
        echo json_encode(['success' => false, 'message' => 'Action non reconnue.']);
        exit;
    }

    public function getDashboardData(): void
    {
        header('Content-Type: application/json');
        
        if (!AuthHelper::estGestionnaire()) {
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }
        
        $gestionnaireId = (int)$_SESSION['gestionnaire_id'];
        $sousService = $this->model->getSousServiceGestionnaire($gestionnaireId);
        
        if (!$sousService) {
            echo json_encode(['error' => 'Sous-service non trouvé']);
            exit;
        }
        
        $ssId = (int)$sousService['id'];

        // Répare les consultations sans médecin assigné (medecin_id NULL ou 0)
        // pour qu'elles deviennent visibles côté médecin dès ce rafraîchissement,
        // sans attendre que le médecin ouvre lui-même son dashboard.
        $this->model->affecterConsultationsSansMedecinDuJour($ssId);

        $stats         = $this->model->statsJour($ssId);
        $file          = $this->model->fileAttente($ssId);
        $consultations = $this->model->consultationsJour($ssId);
        
        foreach ($file as &$c) {
            $c['heure_passage_estimee'] = $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : '—';
            if ($c['statut'] === 'en_pause' && !empty($c['heure_pause'])) {
                $c['secondes_en_pause'] = (int)(time() - strtotime($c['heure_pause']));
                $c['heure_pause_fmt']   = date('H:i', strtotime($c['heure_pause']));
            } else {
                $c['secondes_en_pause'] = 0;
                $c['heure_pause_fmt']   = null;
            }
            $c['priorite_retour'] = (int)($c['priorite_retour'] ?? 0);
        }
        unset($c);
        foreach ($consultations as &$c) {
            $c['heure_passage_estimee'] = $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : '—';
            if ($c['statut'] === 'en_pause' && !empty($c['heure_pause'])) {
                $c['secondes_en_pause'] = (int)(time() - strtotime($c['heure_pause']));
                $c['heure_pause_fmt']   = date('H:i', strtotime($c['heure_pause']));
            } else {
                $c['secondes_en_pause'] = 0;
                $c['heure_pause_fmt']   = null;
            }
            $c['priorite_retour'] = (int)($c['priorite_retour'] ?? 0);
        }
        unset($c);

        $medecins = $this->model->getMedecinsDisponibles($ssId);

        echo json_encode([
            'success'       => true,
            'stats'         => $stats,
            'file'          => $file,
            'consultations' => $consultations,
            'medecins'      => $medecins,
        ]);
        exit;
    }

    public function verifierMdp(): void
    {
        header('Content-Type: application/json');

        $password = $_POST['password'] ?? '';

        if (!AuthHelper::estGestionnaire()) {
            echo json_encode(['success' => false]);
            exit;
        }

        $userId = (int)($_SESSION['user_id'] ?? 0);

        if (!$userId) {
            echo json_encode(['success' => false]);
            exit;
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare(
            'SELECT mot_de_passe FROM utilisateurs WHERE id = :id LIMIT 1'
        );

        $stmt->execute([':id' => $userId]);

        $hash = $stmt->fetchColumn();

        echo json_encode([
            'success' => $hash && password_verify($password, $hash)
        ]);

        exit;
    }

    public function getProfilData(): void
    {
        header('Content-Type: application/json');
        
        if (!AuthHelper::estGestionnaire()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $gestionnaireId = $_SESSION['gestionnaire_id'];
        $gestionnaire = $this->model->trouverParId($gestionnaireId);
        
        if (!$gestionnaire) {
            echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'profil' => [
                'nom' => $gestionnaire['nom'],
                'telephone' => $gestionnaire['telephone'],
                'email' => $gestionnaire['email']
            ]
        ]);
        exit;
    }

    public function mettreAJourProfil(): void
    {
        header('Content-Type: application/json');
        
        if (!AuthHelper::estGestionnaire()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $id = $_SESSION['gestionnaire_id'];
        $nom = trim($_POST['nom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $passwordActuel = $_POST['password_actuel'] ?? '';
        $nouveauPassword = $_POST['nouveau_password'] ?? '';
        $confirmerPassword = $_POST['confirmer_password'] ?? '';

        $erreurs = [];

        if (mb_strlen($nom) < 2) $erreurs['nom'] = 'Nom trop court.';
        if (!preg_match('/^\+?[0-9]{8,19}$/', $telephone)) $erreurs['telephone'] = 'Téléphone invalide.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erreurs['email'] = 'Email invalide.';

        $gestionnaire = $this->model->trouverParId($id);
        if ($email !== $gestionnaire['email'] && $this->model->emailExiste($email)) {
            $erreurs['email'] = 'Cet email est déjà utilisé.';
        }
        if ($telephone !== $gestionnaire['telephone'] && $this->model->telephoneExiste($telephone)) {
            $erreurs['telephone'] = 'Ce numéro est déjà utilisé.';
        }

        if (!empty($nouveauPassword)) {
            if (empty($passwordActuel)) {
                $erreurs['password_actuel'] = 'Veuillez entrer votre mot de passe actuel.';
            } elseif (!$this->model->verifierMotDePasse($id, $passwordActuel)) {
                $erreurs['password_actuel'] = 'Mot de passe actuel incorrect.';
            } elseif (strlen($nouveauPassword) < 8) {
                $erreurs['nouveau_password'] = 'Minimum 8 caractères.';
            } elseif (!preg_match('/[A-Z]/', $nouveauPassword)) {
                $erreurs['nouveau_password'] = 'Au moins une majuscule.';
            } elseif (!preg_match('/[0-9]/', $nouveauPassword)) {
                $erreurs['nouveau_password'] = 'Au moins un chiffre.';
            } elseif ($nouveauPassword !== $confirmerPassword) {
                $erreurs['confirmer_password'] = 'Les mots de passe ne correspondent pas.';
            }
        }

        if (!empty($erreurs)) {
            echo json_encode(['success' => false, 'errors' => $erreurs]);
            exit;
        }

        $this->model->mettreAJourProfil($id, $nom, $telephone);
        $this->model->mettreAJourEmail($id, $email);
        
        // Mettre à jour l'email dans la table utilisateurs
        $user = $this->utilisateurModel->findByEmail($gestionnaire['email']);
        if ($user) {
            $db = Database::getInstance()->getConnection();
            $sql = "UPDATE utilisateurs SET email = :email, nom = :nom WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':email' => $email,
                ':nom' => $nom,
                ':id' => $user['id']
            ]);
        }
        
        if (!empty($nouveauPassword)) {
            $hashedPassword = password_hash($nouveauPassword, PASSWORD_DEFAULT);
            $this->model->mettreAJourMotDePasse($id, $hashedPassword);
            // Mettre à jour le mot de passe dans la table utilisateurs
            if ($user) {
                $db = Database::getInstance()->getConnection();
                $sql = "UPDATE utilisateurs SET mot_de_passe = :password WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':password' => $hashedPassword,
                    ':id' => $user['id']
                ]);
            }
        }

        $_SESSION['user_nom'] = $nom;

        echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès.']);
        exit;
    }

    public function changerLangue(): void
    {
        header('Content-Type: application/json');
        if (!AuthHelper::estGestionnaire()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }
        $langue = $_POST['langue'] ?? 'fr';
        $id = (int)$_SESSION['gestionnaire_id'];
        $this->model->mettreAJourLangue($id, $langue);
        LangHelper::setLang($langue);
        echo json_encode(['success' => true, 'langue' => LangHelper::getLang()]);
        exit;
    }

    public function getEmploiTemps(): void
    {
        header('Content-Type: application/json');
        
        if (!AuthHelper::estGestionnaire()) {
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }

        $gestionnaireId = $_SESSION['gestionnaire_id'];
        $sousService = $this->model->getSousServiceByGestionnaire($gestionnaireId);
        
        if (!$sousService) {
            echo json_encode(['error' => 'Sous-service non trouvé']);
            exit;
        }
        
        $ssId = $sousService['id'];
        $date = $_GET['date'] ?? date('Y-m-d');
        $filtreMemdecinId = isset($_GET['medecin_id']) && is_numeric($_GET['medecin_id']) ? (int)$_GET['medecin_id'] : null;

        // Charger les consultations sur toute la semaine (lundi → dimanche)
        $dateFin = date('Y-m-d', strtotime($date . ' +6 days'));

        // Réaffecte les consultations encore sans médecin (medecin_id NULL) sur
        // toute la semaine affichée : sans cela, une consultation créée avant
        // qu'un médecin ne soit "disponible" (sur place, QR code, en ligne)
        // reste invisible dans ce planning, notamment dès qu'un filtre médecin
        // est appliqué.
        $dateCourante = new DateTimeImmutable($date);
        $dateLimite = new DateTimeImmutable($dateFin);
        while ($dateCourante <= $dateLimite) {
            $this->model->affecterConsultationsSansMedecinDuJour($ssId, $dateCourante->format('Y-m-d'));
            $dateCourante = $dateCourante->modify('+1 day');
        }

        $tousMedecins = $this->model->getMedecinsDisponibles($ssId);
        
        // Si un médecin est sélectionné, on filtre la liste
        $medecins = $filtreMemdecinId
            ? array_values(array_filter($tousMedecins, fn($m) => (int)$m['id'] === $filtreMemdecinId))
            : $tousMedecins;
        
        $consultationsRaw = $this->model->consultationsParPeriode($ssId, $date, $dateFin);

        $consultations = [];
        foreach ($consultationsRaw as $c) {
            if (!$c['heure_passage_estimee']) continue;
            // Filtrer par médecin si demandé
            if ($filtreMemdecinId && isset($c['medecin_id']) && (int)$c['medecin_id'] !== $filtreMemdecinId) {
                continue;
            }
            $heureDebutTs = strtotime($c['heure_passage_estimee']);
            $duree        = (int)($c['duree_estimee'] ?? 1800);
            $consultations[] = [
                'id'                => $c['id'],
                'patient_nom'       => $c['patient_nom'],
                'patient_prenom'    => $c['patient_prenom'],
                'statut'            => $c['statut'],
                'heure_debut'       => date('H:i', $heureDebutTs),
                'heure_fin'         => date('H:i', $heureDebutTs + $duree),
                'date_consultation' => date('Y-m-d', $heureDebutTs),
                'medecin_nom'       => $c['medecin_nom'] ?? 'Non assigné',
                'medecin_id'        => $c['medecin_id'] ?? null,
            ];
        }

        // Charger les plages horaires pour toute la semaine
        $plagesHoraire = [];
        foreach ($medecins as $medecin) {
            $plagesHoraire[$medecin['id']] = [];
            for ($i = 0; $i <= 6; $i++) {
                $dateCourante = date('Y-m-d', strtotime($date . " +{$i} days"));
                $plages = $this->model->getHorairesJour($ssId, $dateCourante);
                foreach ($plages as $plage) {
                    if ($plage['medecin_id'] == $medecin['id']) {
                        $plagesHoraire[$medecin['id']][] = [
                            'jour'        => $plage['jour'],
                            'heure_debut' => $plage['heure_debut'],
                            'heure_fin'   => $plage['heure_fin']
                        ];
                    }
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'medecins' => $medecins,
            'consultations' => $consultations,
            'plages_horaire' => $plagesHoraire,
            'date_debut' => $date,
            'service_horaires' => $this->model->getServiceHoraires($ssId),
            'jours_travail' => array_reduce($medecins, function($carry, $m) {
                $carry[(int)$m['id']] = $this->model->getJoursTravailMedecin((int)$m['id']);
                return $carry;
            }, [])
        ]);
        exit;
    }

    public function getPlanning(): void
    {
        header('Content-Type: application/json');
        
        if (!AuthHelper::estGestionnaire()) {
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }
        
        $date = $_GET['date'] ?? date('Y-m-d');
        $sousServiceId = $_SESSION['sous_service_id'] ?? null;
        
        if (!$sousServiceId) {
            $gestionnaireId = $_SESSION['gestionnaire_id'];
            $sousService = $this->model->getSousServiceByGestionnaire($gestionnaireId);
            $sousServiceId = $sousService['id'] ?? null;
            $_SESSION['sous_service_id'] = $sousServiceId;
        }
        
        if (!$sousServiceId) {
            echo json_encode(['error' => 'Sous-service non trouvé']);
            exit;
        }
        
        $horaires = $this->model->getHorairesJour($sousServiceId, $date);
        $medecins = $this->model->getMedecinsDisponibles($sousServiceId);
        
        echo json_encode([
            'success' => true,
            'horaires' => $horaires,
            'medecins' => $medecins,
            'date' => $date
        ]);
        exit;
    }

    public function getHistorique(): void
    {
        header('Content-Type: application/json');
        if (!AuthHelper::estGestionnaire()) {
            echo json_encode(['redirect' => 'gestionnaire.php?action=connexion']);
            exit;
        }

        $gestionnaireId = $_SESSION['gestionnaire_id'];
        $sousService    = $this->model->getSousServiceByGestionnaire($gestionnaireId);

        if (!$sousService) {
            echo json_encode(['success' => false, 'message' => 'Sous-service introuvable']);
            exit;
        }

        $ssId      = $sousService['id'];
        $page      = max(1, (int)($_GET['page'] ?? 1));
        $perPage   = min(1000, max(1, (int)($_GET['per_page'] ?? 15)));
        $statut    = trim($_GET['statut'] ?? '');
        $dateDebut = trim($_GET['date_debut'] ?? '');
        $dateFin   = trim($_GET['date_fin'] ?? '');

        $result = $this->model->historiquePagine($ssId, $page, $perPage, $statut, $dateDebut, $dateFin);
        $counts = $this->model->historiqueCounts($ssId, $dateDebut, $dateFin);

        foreach ($result['data'] as &$row) {
            $row['date_consultation'] = $row['heure_passage_estimee']
                ? date('d/m/Y', strtotime($row['heure_passage_estimee'])) : '—';
            $row['heure_estimee_fmt'] = $row['heure_passage_estimee']
                ? date('H:i', strtotime($row['heure_passage_estimee'])) : '—';
            $row['heure_debut_fmt'] = $row['heure_debut_reelle']
                ? date('H:i', strtotime($row['heure_debut_reelle'])) : '—';
            $row['heure_fin_fmt'] = $row['heure_fin_reelle']
                ? date('H:i', strtotime($row['heure_fin_reelle'])) : '—';
            if ($row['heure_debut_reelle'] && $row['heure_fin_reelle']) {
                $duree = (strtotime($row['heure_fin_reelle']) - strtotime($row['heure_debut_reelle'])) / 60;
                $row['duree_fmt'] = round($duree) . ' min';
            } else {
                $row['duree_fmt'] = '—';
            }
        }
        unset($row);

        echo json_encode(array_merge(['success' => true, 'counts' => $counts], $result));
        exit;
    }

    /**
     * Évolution + totaux des consultations sur N jours, pour TOUT le
     * sous-service du gestionnaire (graphiques "Évolution des consultations",
     * "Répartition par statut" et "Consultations par jour de semaine" de la
     * section Statistiques du dashboard gestionnaire).
     */
    public function getStatsEvolutionSS(): void
    {
        header('Content-Type: application/json');
        if (!AuthHelper::estGestionnaire()) {
            echo json_encode(['success' => false, 'redirect' => 'gestionnaire.php?action=connexion']);
            exit;
        }

        $gestionnaireId = (int)$_SESSION['gestionnaire_id'];
        $sousService    = $this->model->getSousServiceGestionnaire($gestionnaireId);
        if (!$sousService) {
            echo json_encode(['success' => false, 'message' => 'Sous-service introuvable']);
            exit;
        }

        $ssId      = (int)$sousService['id'];
        // jours=0 = "Aujourd'hui"
        $jours     = max(0, min(365, (int)($_GET['jours'] ?? 7)));
        $evolution = $this->model->statsEvolutionSS($ssId, $jours);
        $totaux    = $this->model->statsTotalesSS($ssId, $jours);
        $parMedecin = $this->model->repartitionParMedecin($ssId, $jours);

        echo json_encode([
            'success'    => true,
            'jours'      => $jours,
            'evolution'  => $evolution,
            'totaux'     => $totaux,
            'par_medecin' => $parMedecin,
        ]);
        exit;
    }

    /**
     * Évolution du temps d'attente moyen pour TOUT le sous-service
     * (section Statistiques du dashboard gestionnaire).
     */
    public function getTempsAttenteEvolutionSS(): void
    {
        header('Content-Type: application/json');
        if (!AuthHelper::estGestionnaire()) {
            echo json_encode(['success' => false, 'redirect' => 'gestionnaire.php?action=connexion']);
            exit;
        }

        $gestionnaireId = (int)$_SESSION['gestionnaire_id'];
        $sousService    = $this->model->getSousServiceGestionnaire($gestionnaireId);
        if (!$sousService) {
            echo json_encode(['success' => false, 'message' => 'Sous-service introuvable']);
            exit;
        }

        $ssId      = (int)$sousService['id'];
        $jours     = max(7, min(365, (int)($_GET['jours'] ?? 30)));
        $evolution = $this->model->evolutionTempsAttenteSS($ssId, $jours);
        $global    = $this->model->tempsAttenteGlobalSS($ssId);

        echo json_encode([
            'success'      => true,
            'evolution'    => $evolution,
            'global'       => $global,
            'sous_service' => $sousService['nom'] ?? '',
            'jours'        => $jours,
        ]);
        exit;
    }
}