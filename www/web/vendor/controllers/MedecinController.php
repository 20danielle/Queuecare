<?php
/**
 * controllers/MedecinController.php
 * Contrôleur pour l'espace Médecin – Version mono-service avec utilisateurs
 */

require_once __DIR__ . '/../models/MedecinModel.php';
require_once __DIR__ . '/../models/ServiceModel.php';
require_once __DIR__ . '/../models/UtilisateurModel.php';
require_once __DIR__ . '/../helpers/UploadHelper.php';
require_once __DIR__ . '/../helpers/AuthHelper.php';
require_once __DIR__ . '/../helpers/QueueNotificationService.php';

class MedecinController
{
    private MedecinModel $model;
    private ServiceModel $serviceModel;
    private UtilisateurModel $utilisateurModel;

    public function __construct()
    {
        $this->model = new MedecinModel();
        $this->serviceModel = new ServiceModel();
        $this->utilisateurModel = new UtilisateurModel();
    }

    /* ════════════════════════════════════════════════════════
       INSCRIPTION (mono‑service)
    ════════════════════════════════════════════════════════ */

    public function afficherInscription(): void
    {
        // Vérifier si déjà connecté
        if (AuthHelper::estMedecin()) {
            header('Location: medecin.php?action=dashboard');
            exit;
        }
        $erreurs    = [];
        $anciens    = [];
        require __DIR__ . '/../views/medecin/inscription.php';
    }

    public function traiterInscription(): void
    {
        $sousServices = $this->model->getSousServicesActifs();
        $erreurs  = [];

        $nom       = trim($_POST['nom']        ?? '');
        $prenom    = trim($_POST['prenom']      ?? '');
        $telephone = trim($_POST['telephone']   ?? '');
        $email     = trim($_POST['email']       ?? '');
        $password  = $_POST['password']         ?? '';
        $confirm   = $_POST['confirm']          ?? '';
        $specialiteId = (int)($_POST['sous_service_id'] ?? 0);

        $anciens = compact('nom', 'prenom', 'telephone', 'email', 'specialiteId');

        // Upload de la photo
        $photoPath = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload = UploadHelper::uploadPhoto($_FILES['photo'], 'medecins');
            if ($upload['success']) {
                $photoPath = $upload['filename'];
            } else {
                $erreurs['photo'] = $upload['error'];
            }
        }

        // Validations
        if (mb_strlen($nom) < 2) $erreurs['nom'] = 'Nom trop court (min. 2 caractères).';
        if (mb_strlen($prenom) < 2) $erreurs['prenom'] = 'Prénom trop court (min. 2 caractères).';

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

        if ($specialiteId <= 0) {
            $erreurs['sous_service_id'] = 'Veuillez sélectionner votre sous-service.';
        } else {
            $ss = $this->model->trouverSousServiceParId($specialiteId);
            if (!$ss || $ss['statut'] !== 'actif') {
                $erreurs['sous_service_id'] = 'Sous‑service invalide.';
            }
        }

        if (strlen($password) < 8) {
            $erreurs['password'] = 'Minimum 8 caractères.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $erreurs['password'] = 'Au moins une majuscule requise.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $erreurs['password'] = 'Au moins un chiffre requis.';
        }

        if ($password !== $confirm) $erreurs['confirm'] = 'Les mots de passe ne correspondent pas.';

        if (!empty($erreurs)) {
            require __DIR__ . '/../views/medecin/inscription.php';
            return;
        }

        // 🔐 HASHER LE MOT DE PASSE AVANT STOCKAGE
        // 1. Créer le médecin (MedecinModel hache lui-même le mot de passe)
        $medecinId = $this->model->creer([
            'nom'        => $nom,
            'prenom'     => $prenom,
            'specialite' => $ss['nom'],
            'telephone'  => $telephone,
            'email'      => $email,
            'password'   => $password,
            'photo'      => $photoPath,
        ]);

        if (!$medecinId) {
            $erreurs['global'] = 'Erreur lors de la création du compte médecin.';
            require __DIR__ . '/../views/medecin/inscription.php';
            return;
        }

        // 2. Affecter le sous-service
        $this->model->affecterSousService($medecinId, $specialiteId);

        // 3. Créer le compte utilisateur — récupérer le hash déjà stocké dans medecins
        $medecinData = $this->model->trouverParEmail($email);
        $userId = $this->utilisateurModel->creer([
            'email'      => $email,
            'password'   => $medecinData['password'],
            'role'       => 'medecin',
            'nom'        => $prenom . ' ' . $nom,
            'medecin_id' => $medecinId
        ]);

        if (!$userId) {
            $erreurs['global'] = 'Erreur lors de la création du compte utilisateur.';
            require __DIR__ . '/../views/medecin/inscription.php';
            return;
        }

        // 4. Connecter automatiquement
        AuthHelper::connecterMedecin($medecinId, $prenom . ' ' . $nom, $email);
        $_SESSION['user_id'] = $userId;

        header('Location: medecin.php?action=dashboard');
        exit;
    }

    /* ════════════════════════════════════════════════════════
       CONNEXION
    ════════════════════════════════════════════════════════ */

    public function afficherConnexion(): void
    {
        if (AuthHelper::estMedecin()) {
            header('Location: medecin.php?action=dashboard');
            exit;
        }
        
        $erreurs      = [];
        $ancien_email = '';
        require __DIR__ . '/../views/medecin/connexion.php';
    }

    public function traiterConnexion(): void
    {
        $erreurs      = [];
        $ancien_email = trim($_POST['email']  ?? '');
        $password     = $_POST['password']    ?? '';

        if (empty($ancien_email) || empty($password)) {
            $erreurs['global'] = 'Veuillez remplir tous les champs.';
            require __DIR__ . '/../views/medecin/connexion.php';
            return;
        }

        // Authentifier via la table utilisateurs
        $user = $this->utilisateurModel->authentifier($ancien_email, $password);

        if (!$user || ($user['role'] !== 'medecin' && $user['role'] !== 'admin')) {
            $erreurs['global'] = 'Email ou mot de passe incorrect.';
            require __DIR__ . '/../views/medecin/connexion.php';
            return;
        }

        $medecin = $this->model->trouverParId($user['medecin_id']);
        if (!$medecin) {
            $erreurs['global'] = 'Compte médecin introuvable.';
            require __DIR__ . '/../views/medecin/connexion.php';
            return;
        }

        // Mettre à jour la dernière connexion
        $this->utilisateurModel->updateDerniereConnexion($user['id']);

        // Stocker le vrai user_id (table utilisateurs) + medecin_id séparé
        AuthHelper::initSession();
        $_SESSION['user_id']       = $user['id'];
        $_SESSION['user_nom']      = $user['nom'];
        $_SESSION['user_email']    = $user['email'];
        $_SESSION['medecin_id']    = $user['medecin_id'];
        $_SESSION['role']          = $user['role'];
        $_SESSION['last_activity'] = time();

        header('Location: medecin.php?action=dashboard');
        exit;
    }

    /* ════════════════════════════════════════════════════════
       DASHBOARD
    ════════════════════════════════════════════════════════ */

    public function afficherDashboard(): void
    {
        if (!AuthHelper::peutAccederEspaceMedecin()) {
            header('Location: medecin.php?action=connexion');
            exit;
        }
        // Mode admin-medecin : signaler a la vue qu'on est dans le dashboard admin
        $isAdminMedecin = AuthHelper::estAdminMedecin();

        $medecinId  = (int)$_SESSION['medecin_id'];
        $medecin    = $this->model->trouverParId($medecinId);
        $affectation = $this->model->getSousServiceMedecin($medecinId);
        
        // Récupérer les jours de travail du médecin
        $medecin['jours_travail'] = $this->model->getJoursTravailMedecin($medecinId);
        
        // Récupérer les horaires du service (id=1)
        $service = $this->serviceModel->getServiceById(1);
        if (!$service) {
            $this->serviceModel->creerServiceParDefaut();
            $service = $this->serviceModel->getServiceById(1);
        }
        
        $stats      = [];
        $consultations = [];
        $planning   = [];

        if ($affectation) {
            $ssId = (int)$affectation['ss_id'];
            $this->model->affecterConsultationsSansMedecinDuJour($ssId);
            $stats = $this->model->statsJour($ssId, $medecinId);
            $consultations = $this->model->consultationsDuJour($ssId, $medecinId);
            $planning = $this->model->planningMedecin($medecinId);
        }

        require __DIR__ . '/../views/medecin/dashboard.php';
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
    // MÉTHODES AJAX (getConsultationsData, getStatsData, etc.) - Inchangées
    // ═══════════════════════════════════════════════════════════════════════════════
    
    public function getConsultationsData(): void
    {
        header('Content-Type: application/json');

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }

        $medecinId   = $_SESSION['medecin_id'];
        $affectation = $this->model->getSousServiceMedecin($medecinId);

        if (!$affectation) {
            echo json_encode(['success' => true, 'consultations' => [], 'stats' => []]);
            exit;
        }

        $ssId = $affectation['ss_id'];
        $this->model->affecterConsultationsSansMedecinDuJour($ssId);

        $consultations = $this->model->consultationsDuJour($ssId, $medecinId);
        $stats         = $this->model->statsJour($ssId, (int)$medecinId);

        foreach ($consultations as &$c) {
            $c['heure_passage_estimee'] = $c['heure_passage_estimee'] ? date('H:i', strtotime($c['heure_passage_estimee'])) : '—';
            $c['heure_debut_reelle'] = $c['heure_debut_reelle'] ? date('H:i', strtotime($c['heure_debut_reelle'])) : '—';
            $c['heure_fin_reelle'] = $c['heure_fin_reelle'] ? date('H:i', strtotime($c['heure_fin_reelle'])) : '—';
            // Calcul côté serveur (doublon de sécurité si la DB ne le retourne pas)
            if ($c['statut'] === 'en_pause' && !empty($c['heure_pause'])) {
                $c['secondes_en_pause'] = (int)(time() - strtotime($c['heure_pause']));
            } else {
                $c['secondes_en_pause'] = (int)($c['secondes_en_pause'] ?? 0);
            }
            $c['priorite_retour'] = (int)($c['priorite_retour'] ?? 0);
        }
        unset($c);

        echo json_encode(['success' => true, 'consultations' => $consultations, 'stats' => $stats]);
        exit;
    }

    public function getStatsData(): void
    {
        header('Content-Type: application/json');

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }

        $medecinId   = $_SESSION['medecin_id'];
        $affectation = $this->model->getSousServiceMedecin($medecinId);

        if (!$affectation) {
            echo json_encode(['success' => true, 'stats' => []]);
            exit;
        }

        $stats = $this->model->statsJour($affectation['ss_id'], (int)$medecinId);
        echo json_encode(['success' => true, 'stats' => $stats]);
        exit;
    }

    public function mettreEnPauseAjax(): void
    {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $consultationId = (int)($_POST['consultation_id'] ?? 0);
        $motif          = trim($_POST['motif'] ?? 'Examen externe');

        if ($consultationId && $this->model->mettreEnPause($consultationId, $motif)) {
            // Notification push : patient informé de sa mise en pause
            $affectation = $this->model->getSousServiceMedecin((int)$_SESSION['medecin_id']);
            $ssId = $affectation ? (int)$affectation['ss_id'] : 0;
            if ($ssId > 0) {
                try {
                    $notifSvc = new QueueNotificationService();
                    $notifSvc->onMiseEnPause($consultationId, $ssId, $motif);
                } catch (\Throwable $e) {
                    error_log('[FCM] mettreEnPause: ' . $e->getMessage());
                }
            }
            echo json_encode(['success' => true, 'message' => 'Consultation mise en pause']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Impossible de mettre en pause (la consultation doit être en cours)']);
        }
        exit;
    }

    public function reprendreConsultationAjax(): void
    {
        if (ob_get_level()) ob_end_clean();
        header('Content-Type: application/json');

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $consultationId = (int)($_POST['consultation_id'] ?? 0);
        if ($consultationId && $this->model->reprendreConsultation($consultationId)) {
            echo json_encode(['success' => true, 'message' => 'Consultation reprise']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Impossible de reprendre cette consultation']);
        }
        exit;
    }

    public function demarrerConsultationAjax(): void
    {
        header('Content-Type: application/json');

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $consultationId = (int)($_POST['consultation_id'] ?? 0);

        if ($consultationId && $this->model->demarrerConsultation($consultationId)) {
            echo json_encode(['success' => true, 'message' => 'Consultation démarrée']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Impossible de démarrer cette consultation']);
        }
        exit;
    }

    public function terminerConsultationAjax(): void
    {
        header('Content-Type: application/json');

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $consultationId = (int)($_POST['consultation_id'] ?? 0);
        if (!$consultationId) {
            echo json_encode(['success' => false, 'message' => 'Consultation introuvable']);
            exit;
        }

        // Récupérer le sous-service AVANT de terminer (pour notifier la file)
        $affectation = $this->model->getSousServiceMedecin((int)$_SESSION['medecin_id']);
        $ssId = $affectation ? (int)$affectation['ss_id'] : 0;

        if ($this->model->terminerConsultation($consultationId)) {
            // Notifications push : avancement de toute la file
            if ($ssId > 0) {
                try {
                    $notifSvc = new QueueNotificationService();
                    $notifSvc->onConsultationTerminee($consultationId, $ssId);
                } catch (\Throwable $e) {
                    error_log('[FCM] terminerConsultation: ' . $e->getMessage());
                }
            }
            echo json_encode(['success' => true, 'message' => 'Consultation terminée']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Impossible de terminer cette consultation']);
        }
        exit;
    }

    public function marquerAbsentAjax(): void
    {
        header('Content-Type: application/json');

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $consultationId = (int)($_POST['consultation_id'] ?? 0);
        if (!$consultationId) {
            echo json_encode(['success' => false, 'message' => 'Consultation introuvable']);
            exit;
        }

        $affectation = $this->model->getSousServiceMedecin((int)$_SESSION['medecin_id']);
        $ssId = $affectation ? (int)$affectation['ss_id'] : 0;

        if ($this->model->marquerAbsent($consultationId)) {
            // Notifications push : absent + avancement de la file
            if ($ssId > 0) {
                try {
                    $notifSvc = new QueueNotificationService();
                    $notifSvc->onPatientAbsent($consultationId, $ssId);
                } catch (\Throwable $e) {
                    error_log('[FCM] marquerAbsent: ' . $e->getMessage());
                }
            }
            echo json_encode(['success' => true, 'message' => 'Patient marqué comme absent']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Impossible de marquer ce patient comme absent']);
        }
        exit;
    }

    public function annulerToutesAjax(): void
    {
        header('Content-Type: application/json');

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $medecinId = $_SESSION['medecin_id'];
        if ($this->model->annulerToutesConsultations($medecinId)) {
            echo json_encode(['success' => true, 'message' => 'Consultations reportées au lendemain avec priorité']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erreur lors du report des consultations']);
        }
        exit;
    }
    public function getPlanningMedecin(): void
    {
        header('Content-Type: application/json');

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['error' => 'Non authentifié']);
            exit;
        }

        $medecinId = $_SESSION['medecin_id'];
        $affectation = $this->model->getSousServiceMedecin($medecinId);

        if (!$affectation) {
            echo json_encode(['success' => true, 'consultations' => []]);
            exit;
        }

        $ssId = $affectation['ss_id'];
        $date = $_GET['date'] ?? date('Y-m-d');
        $startDate = date('Y-m-d', strtotime($date));
        $endDate = date('Y-m-d', strtotime($date . ' +6 days'));

        $dateCourante = new DateTimeImmutable($startDate);
        $dateLimite = new DateTimeImmutable($endDate);
        while ($dateCourante <= $dateLimite) {
            $this->model->affecterConsultationsSansMedecinDuJour($ssId, $dateCourante->format('Y-m-d'));
            $dateCourante = $dateCourante->modify('+1 day');
        }

        $consultationsData = $this->model->getConsultationsParPeriode($ssId, $medecinId, $startDate, $endDate);
        
        $consultations = [];
        foreach ($consultationsData as $c) {
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
            ];
        }

        // Service horaires + jours de travail du médecin
        $serviceHoraires = $this->model->getServiceHoraires($ssId);
        $joursTravail    = $this->model->getJoursTravailMedecin((int)$medecinId);

        echo json_encode([
            'success'          => true,
            'consultations'    => $consultations,
            'date_debut'       => $date,
            'service_horaires' => $serviceHoraires,
            'jours_travail'    => $joursTravail,
        ]);
        exit;
    }

    public function verifierMdp(): void
    {
        header('Content-Type: application/json');

        $password = $_POST['password'] ?? '';

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode([
                'success' => false,
                'message' => 'Non authentifié'
            ]);
            exit;
        }

        $medecin = $this->model->trouverParId(
            (int)$_SESSION['medecin_id']
        );

        if (!$medecin) {
            echo json_encode([
                'success' => false
            ]);
            exit;
        }

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT mot_de_passe
            FROM utilisateurs
            WHERE medecin_id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':id' => $_SESSION['medecin_id']
        ]);

        $hash = $stmt->fetchColumn();

        echo json_encode([
            'success' => $hash && password_verify($password, $hash)
        ]);

        exit;
    }

    public function getProfilData(): void
    {
        header('Content-Type: application/json');

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $medecinId = $_SESSION['medecin_id'];
        $medecin = $this->model->trouverParId($medecinId);

        if (!$medecin) {
            echo json_encode(['success' => false, 'message' => 'Utilisateur non trouvé']);
            exit;
        }

        echo json_encode([
            'success' => true,
            'profil' => [
                'nom' => $medecin['nom'],
                'prenom' => $medecin['prenom'],
                'telephone' => $medecin['telephone'],
                'email' => $medecin['email'],
                'photo' => $medecin['photo']
            ]
        ]);
        exit;
    }

    public function mettreAJourProfil(): void
    {
        header('Content-Type: application/json');

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $id = $_SESSION['medecin_id'];
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        $telephone = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $passwordActuel = $_POST['password_actuel'] ?? '';
        $nouveauPassword = $_POST['nouveau_password'] ?? '';
        $confirmerPassword = $_POST['confirmer_password'] ?? '';

        $erreurs = [];

        if (mb_strlen($nom) < 2) $erreurs['nom'] = 'Nom trop court.';
        if (mb_strlen($prenom) < 2) $erreurs['prenom'] = 'Prénom trop court.';
        if (!preg_match('/^\+?[0-9]{8,19}$/', $telephone)) $erreurs['telephone'] = 'Téléphone invalide.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $erreurs['email'] = 'Email invalide.';

        $medecin = $this->model->trouverParId($id);
        if ($email !== $medecin['email'] && $this->model->emailExiste($email)) {
            $erreurs['email'] = 'Cet email est déjà utilisé.';
        }
        if ($telephone !== $medecin['telephone'] && $this->model->telephoneExiste($telephone)) {
            $erreurs['telephone'] = 'Ce numéro est déjà utilisé.';
        }

        // Gestion de la photo
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $upload = UploadHelper::uploadPhoto($_FILES['photo'], 'medecins');
            if ($upload['success']) {
                if ($medecin['photo'] && file_exists(__DIR__ . '/../' . $medecin['photo'])) {
                    unlink(__DIR__ . '/../' . $medecin['photo']);
                }
                $this->model->mettreAJourPhoto($id, $upload['filename']);
            } else {
                $erreurs['photo'] = $upload['error'];
            }
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

        $this->model->mettreAJourProfil($id, $nom, $prenom, $telephone);
        $this->model->mettreAJourEmail($id, $email);

        // Mettre à jour l'email dans la table utilisateurs
        $user = $this->utilisateurModel->findByEmail($medecin['email']);
        if ($user) {
            $db = Database::getInstance()->getConnection();
            $sql = "UPDATE utilisateurs SET email = :email, nom = :nom WHERE id = :id";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':email' => $email,
                ':nom' => $prenom . ' ' . $nom,
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

        $_SESSION['user_nom'] = $prenom . ' ' . $nom;

        echo json_encode(['success' => true, 'message' => 'Profil mis à jour avec succès.']);
        exit;
    }
    
    public function apiSousServices(): void
    {
        header('Content-Type: application/json');
        $sousServices = $this->model->getSousServicesActifs();
        echo json_encode($sousServices);
        exit;
    }

    /* ════════════════════════════════════════════════════════
       PROCHAIN RDV
    ════════════════════════════════════════════════════════ */

    /**
     * Retourne les créneaux disponibles pour une date donnée (AJAX GET).
     */
    public function getCreneauxDisponibles(): void
    {
        header('Content-Type: application/json');

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $medecinId = (int)$_SESSION['medecin_id'];
        $date = trim($_GET['date'] ?? '');

        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Date invalide.']);
            exit;
        }

        // Pas de RDV dans le passé
        if ($date <= date('Y-m-d')) {
            echo json_encode(['success' => false, 'message' => 'La date doit être dans le futur.', 'creneaux' => []]);
            exit;
        }

        $creneaux = $this->model->getCreneauxDisponibles($medecinId, $date);
        echo json_encode(['success' => true, 'creneaux' => $creneaux]);
        exit;
    }

    /**
     * Crée le prochain RDV depuis une consultation (AJAX POST).
     */
    public function fixerProchainRdvAjax(): void
    {
        header('Content-Type: application/json');

        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }

        $consultationId = (int)($_POST['consultation_id'] ?? 0);
        $dateRdv        = trim($_POST['date_rdv'] ?? '');
        $heureRdv       = trim($_POST['heure_rdv'] ?? '');
        $motif          = trim($_POST['motif'] ?? '');

        if (!$consultationId) {
            echo json_encode(['success' => false, 'message' => 'Consultation introuvable.']);
            exit;
        }
        if (!$dateRdv || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateRdv)) {
            echo json_encode(['success' => false, 'message' => 'Date invalide.']);
            exit;
        }
        if (!$heureRdv || !preg_match('/^\d{2}:\d{2}$/', $heureRdv)) {
            echo json_encode(['success' => false, 'message' => 'Heure invalide.']);
            exit;
        }
        if ($dateRdv <= date('Y-m-d')) {
            echo json_encode(['success' => false, 'message' => 'La date doit être dans le futur.']);
            exit;
        }

        $result = $this->model->fixerProchainRdv($consultationId, $dateRdv, $heureRdv, $motif);
        echo json_encode($result);
        exit;
    }

    public function getHistorique(): void
    {
        header('Content-Type: application/json');
        if (!AuthHelper::peutAccederEspaceMedecin()) {
            echo json_encode(['redirect' => 'medecin.php?action=connexion']);
            exit;
        }

        $medecinId  = $_SESSION['medecin_id'];
        $page       = max(1, (int)($_GET['page'] ?? 1));
        $perPage    = 15;
        $statut     = trim($_GET['statut'] ?? '');
        $dateDebut  = trim($_GET['date_debut'] ?? '');
        $dateFin    = trim($_GET['date_fin'] ?? '');

        $result = $this->model->historiquePagine($medecinId, $page, $perPage, $statut, $dateDebut, $dateFin);
        $counts = $this->model->historiqueCounts($medecinId, $dateDebut, $dateFin);

        // Formater les dates pour l'affichage
        foreach ($result['data'] as &$row) {
            $row['date_consultation'] = $row['heure_passage_estimee']
                ? date('d/m/Y', strtotime($row['heure_passage_estimee'])) : '—';
            $row['heure_estimee_fmt'] = $row['heure_passage_estimee']
                ? date('H:i', strtotime($row['heure_passage_estimee'])) : '—';
            $row['heure_debut_fmt'] = $row['heure_debut_reelle']
                ? date('H:i', strtotime($row['heure_debut_reelle'])) : '—';
            $row['heure_fin_fmt'] = $row['heure_fin_reelle']
                ? date('H:i', strtotime($row['heure_fin_reelle'])) : '—';
            // Durée réelle
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
     * Retourne l'évolution des consultations sur N jours pour les graphiques du médecin.
     */
    public function getStatsEvolution(): void
    {
        header('Content-Type: application/json');
        $medecinId = $_SESSION['medecin_id'] ?? null;
        if (!$medecinId) {
            echo json_encode(['success' => false, 'redirect' => 'medecin.php?action=connexion']);
            exit;
        }
        $jours = max(7, min(365, (int)($_GET['jours'] ?? 7)));
        $evolution = $this->model->statsEvolution((int)$medecinId, $jours);
        $totaux    = $this->model->statsTotalesMedecin((int)$medecinId, $jours);
        echo json_encode([
            'success'   => true,
            'jours'     => $jours,
            'evolution' => $evolution,
            'totaux'    => $totaux,
        ]);
        exit;
    }

    /**
     * Retourne l'évolution du temps d'attente moyen pour la section stats médecin
     */
    public function getTempsAttenteEvolution(): void
    {
        header('Content-Type: application/json');
        $medecinId = $_SESSION['medecin_id'] ?? null;
        if (!$medecinId) {
            echo json_encode(['success' => false, 'redirect' => 'medecin.php?action=connexion']);
            exit;
        }
        // Récupérer l'affectation du médecin pour avoir le sous-service
        $affectation = $this->model->getSousServiceMedecin((int)$medecinId);
        if (!$affectation || empty($affectation['ss_id'])) {
            echo json_encode(['success' => false, 'message' => 'Aucun sous-service associé.']);
            exit;
        }
        $sousServiceId = (int)$affectation['ss_id'];
        $jours = max(7, min(365, (int)($_GET['jours'] ?? 30)));
        $evolution = $this->model->evolutionTempsAttente($sousServiceId, $jours);
        $global    = $this->model->tempsAttenteGlobal($sousServiceId);
        echo json_encode([
            'success'         => true,
            'evolution'       => $evolution,
            'global'          => $global,
            'sous_service'    => $affectation['ss_nom'] ?? '',
            'jours'           => $jours,
        ]);
        exit;
    }

    /**
     * Fournit les donnees de consultation pour l'onglet "Mes consultations" du dashboard admin.
     * Accessible uniquement par un admin-medecin.
     */
    public function getDashboardMedecinData(): void
    {
        header('Content-Type: application/json');
        if (!AuthHelper::estAdminMedecin()) {
            echo json_encode(['success' => false, 'message' => 'Acces refuse.']);
            exit;
        }
        $medecinId   = (int)$_SESSION['medecin_id'];
        $affectation = $this->model->getSousServiceMedecin($medecinId);
        $medecin     = $this->model->trouverParId($medecinId);
        $stats       = [];
        $consultations = [];
        if ($affectation) {
            $ssId          = (int)$affectation['ss_id'];
            $stats         = $this->model->statsJour($ssId, $medecinId);
            $consultations = $this->model->consultationsDuJour($ssId, $medecinId);
        }
        echo json_encode([
            'success'       => true,
            'medecin'       => $medecin,
            'affectation'   => $affectation,
            'stats'         => $stats,
            'consultations' => $consultations,
        ]);
        exit;
    }
}