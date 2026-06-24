<?php
/**
 * controllers/PatientController.php
 * API pour les opérations patient (authentification, profil, données)
 * Toutes les méthodes retournent du JSON pour l'app mobile Kotlin
 */

require_once __DIR__ . '/../models/PatientModel.php';
require_once __DIR__ . '/../models/ConsultationModel.php';
require_once __DIR__ . '/../models/NotificationModel.php';
require_once __DIR__ . '/../models/TicketModel.php';
require_once __DIR__ . '/../models/SousServiceModel.php';
require_once __DIR__ . '/../config/database.php';

class PatientController
{
    private PDO $db;
    private PatientModel $patientModel;
    private ConsultationModel $consultationModel;
    private NotificationModel $notificationModel;
    private TicketModel $ticketModel;
    private SousServiceModel $sousServiceModel;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->patientModel = new PatientModel();
        $this->consultationModel = new ConsultationModel();
        $this->notificationModel = new NotificationModel();
        $this->ticketModel = new TicketModel();
        $this->sousServiceModel = new SousServiceModel();
    }

    /* ═══════════════════════════════════════════════════════════
       MÉTHODES UTILITAIRES
    ═══════════════════════════════════════════════════════════ */

    /**
     * Vérifie l'authentification via token Bearer
     * @return int|null ID du patient ou null si non authentifié
     */
    private function getAuthenticatedPatientId(): ?int
    {
        // Vérifier d'abord la session (pour compatibilité web si besoin)
        if (isset($_SESSION['patient_id'])) {
            return (int)$_SESSION['patient_id'];
        }
        
        // Vérifier l'en-tête Authorization
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
            $stmt = $this->db->prepare(
                'SELECT patient_id FROM patient_api_tokens 
                 WHERE token = :token AND expire_at > NOW()'
            );
            $stmt->execute([':token' => $token]);
            $result = $stmt->fetch();
            if ($result) {
                return (int)$result['patient_id'];
            }
        }
        
        return null;
    }

    /**
     * Envoie une réponse JSON standardisée
     */
    private function jsonResponse(bool $success, string $message = '', array $data = [], int $httpCode = 200): void
    {
        http_response_code($httpCode);
        echo json_encode(array_merge([
            'success' => $success,
            'message' => $message
        ], $data));
        exit;
    }

    /* ═══════════════════════════════════════════════════════════
       AUTHENTIFICATION
    ═══════════════════════════════════════════════════════════ */

    /**
     * POST /index.php?action=patient_login
     * Body: {"email": "xxx", "password": "xxx"}
     */
    public function login(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $this->jsonResponse(false, 'Email et mot de passe requis', [], 400);
            return;
        }
        
        $patient = $this->patientModel->verifierConnexion($email, $password);
        
        if (!$patient) {
            $this->jsonResponse(false, 'Email ou mot de passe incorrect', [], 401);
            return;
        }
        
        // Générer un token d'API pour l'app mobile
        $apiToken = bin2hex(random_bytes(32));
        $expireAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        // Supprimer les anciens tokens du patient
        $stmt = $this->db->prepare('DELETE FROM patient_api_tokens WHERE patient_id = :pid');
        $stmt->execute([':pid' => $patient['id']]);
        
        // Créer le nouveau token
        $stmt = $this->db->prepare(
            'INSERT INTO patient_api_tokens (patient_id, token, expire_at) 
             VALUES (:pid, :token, :expire_at)'
        );
        $stmt->execute([
            ':pid' => $patient['id'],
            ':token' => $apiToken,
            ':expire_at' => $expireAt
        ]);
        
        $this->jsonResponse(true, 'Connexion réussie', [
            'patient' => [
                'id' => $patient['id'],
                'nom' => $patient['nom'],
                'prenom' => $patient['prenom'],
                'telephone' => $patient['telephone'],
                'email' => $patient['email'],
                'statut' => $patient['statut'],
                'date_inscription' => $patient['date_inscription']
            ],
            'token' => $apiToken,
            'expires_at' => $expireAt
        ]);
    }

    /**
     * POST /index.php?action=patient_register
     * Body: {"nom": "xxx", "prenom": "xxx", "telephone": "xxx", "email": "xxx", "password": "xxx"}
     */
    public function register(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        $nom = trim($input['nom'] ?? '');
        $prenom = trim($input['prenom'] ?? '');
        $telephone = trim($input['telephone'] ?? '');
        $email = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';
        
        // Validations
        $errors = [];
        
        if (strlen($nom) < 2) {
            $errors['nom'] = 'Le nom doit contenir au moins 2 caractères';
        }
        if (strlen($prenom) < 2) {
            $errors['prenom'] = 'Le prénom doit contenir au moins 2 caractères';
        }
        if (!preg_match('/^\+?[0-9]{8,19}$/', $telephone)) {
            $errors['telephone'] = 'Numéro de téléphone invalide';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Adresse email invalide';
        }
        if ($this->patientModel->emailExiste($email)) {
            $errors['email'] = 'Cette adresse email est déjà utilisée';
        }
        if (strlen($password) < 6) {
            $errors['password'] = 'Le mot de passe doit contenir au moins 6 caractères';
        }
        
        if (!empty($errors)) {
            $this->jsonResponse(false, 'Erreurs de validation', ['errors' => $errors], 400);
            return;
        }
        
        $patientId = $this->patientModel->creer([
            'nom' => $nom,
            'prenom' => $prenom,
            'telephone' => $telephone,
            'email' => $email,
            'password' => $password
        ]);
        
        if (!$patientId) {
            $this->jsonResponse(false, 'Erreur lors de l\'inscription', [], 500);
            return;
        }
        
        // Générer un token d'API
        $apiToken = bin2hex(random_bytes(32));
        $expireAt = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $this->db->prepare(
            'INSERT INTO patient_api_tokens (patient_id, token, expire_at) VALUES (:pid, :token, :expire_at)'
        );
        $stmt->execute([
            ':pid' => $patientId,
            ':token' => $apiToken,
            ':expire_at' => $expireAt
        ]);
        
        $patient = $this->patientModel->trouverParId($patientId);
        
        $this->jsonResponse(true, 'Inscription réussie', [
            'patient' => [
                'id' => $patient['id'],
                'nom' => $patient['nom'],
                'prenom' => $patient['prenom'],
                'telephone' => $patient['telephone'],
                'email' => $patient['email'],
                'statut' => $patient['statut'],
                'date_inscription' => $patient['date_inscription']
            ],
            'token' => $apiToken,
            'expires_at' => $expireAt
        ]);
    }

    /**
     * POST /index.php?action=patient_logout
     * Headers: Authorization: Bearer <token>
     */
    public function logout(): void
    {
        $patientId = $this->getAuthenticatedPatientId();
        
        if (!$patientId) {
            $this->jsonResponse(false, 'Non authentifié', [], 401);
            return;
        }
        
        $stmt = $this->db->prepare('DELETE FROM patient_api_tokens WHERE patient_id = :pid');
        $stmt->execute([':pid' => $patientId]);
        
        // Nettoyer la session si elle existe
        if (isset($_SESSION['patient_id'])) {
            unset($_SESSION['patient_id']);
            unset($_SESSION['patient_nom']);
        }
        
        $this->jsonResponse(true, 'Déconnexion réussie');
    }

    /**
     * GET /index.php?action=patient_profile
     * Headers: Authorization: Bearer <token>
     */
    public function getProfile(): void
    {
        $patientId = $this->getAuthenticatedPatientId();
        
        if (!$patientId) {
            $this->jsonResponse(false, 'Non authentifié', [], 401);
            return;
        }
        
        $patient = $this->patientModel->trouverParId($patientId);
        
        if (!$patient) {
            $this->jsonResponse(false, 'Patient introuvable', [], 404);
            return;
        }
        
        $this->jsonResponse(true, 'Profil récupéré', [
            'patient' => [
                'id' => $patient['id'],
                'nom' => $patient['nom'],
                'prenom' => $patient['prenom'],
                'telephone' => $patient['telephone'],
                'email' => $patient['email'],
                'statut' => $patient['statut'],
                'date_inscription' => $patient['date_inscription']
            ]
        ]);
    }

    /**
     * POST /index.php?action=patient_update_profile
     * Headers: Authorization: Bearer <token>
     * Body: {"nom": "xxx", "prenom": "xxx", "telephone": "xxx", "email": "xxx"}
     */
    public function updateProfile(): void
    {
        $patientId = $this->getAuthenticatedPatientId();
        
        if (!$patientId) {
            $this->jsonResponse(false, 'Non authentifié', [], 401);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        $nom = trim($input['nom'] ?? '');
        $prenom = trim($input['prenom'] ?? '');
        $telephone = trim($input['telephone'] ?? '');
        $email = trim($input['email'] ?? '');
        
        // Validations
        $errors = [];
        if (strlen($nom) < 2) $errors['nom'] = 'Nom trop court';
        if (strlen($prenom) < 2) $errors['prenom'] = 'Prénom trop court';
        if (!preg_match('/^\+?[0-9]{8,19}$/', $telephone)) $errors['telephone'] = 'Téléphone invalide';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Email invalide';
        
        $patient = $this->patientModel->trouverParId($patientId);
        if ($email !== $patient['email'] && $this->patientModel->emailExiste($email)) {
            $errors['email'] = 'Email déjà utilisé';
        }
        if ($telephone !== $patient['telephone'] && $this->patientModel->telephoneExiste($telephone)) {
            $errors['telephone'] = 'Téléphone déjà utilisé';
        }
        
        if (!empty($errors)) {
            $this->jsonResponse(false, 'Erreurs de validation', ['errors' => $errors], 400);
            return;
        }
        
        $success = $this->patientModel->mettreAJour($patientId, [
            'nom' => $nom,
            'prenom' => $prenom,
            'telephone' => $telephone,
            'email' => $email,
            'statut' => 'actif'
        ]);
        
        if (!$success) {
            $this->jsonResponse(false, 'Erreur lors de la mise à jour', [], 500);
            return;
        }
        
        $this->jsonResponse(true, 'Profil mis à jour');
    }

    /* ═══════════════════════════════════════════════════════════
       DONNÉES PATIENT
    ═══════════════════════════════════════════════════════════ */

    /**
     * GET /index.php?action=patient_consultations
     * Headers: Authorization: Bearer <token>
     */
    public function getConsultations(): void
    {
        $patientId = $this->getAuthenticatedPatientId();
        
        if (!$patientId) {
            $this->jsonResponse(false, 'Non authentifié', [], 401);
            return;
        }
        
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $consultations = $this->patientModel->getConsultationsPatient($patientId);
        
        // Formater les dates et injecter le rang DYNAMIQUE (recalculé en
        // temps réel) au lieu du rang statique stocké en base : il diminue
        // automatiquement à chaque consultation terminée devant le patient.
        foreach ($consultations as &$c) {
            $c['heure_passage_estimee'] = $c['heure_passage_estimee'] ? date('Y-m-d H:i:s', strtotime($c['heure_passage_estimee'])) : null;
            $c['heure_debut_reelle'] = $c['heure_debut_reelle'] ? date('Y-m-d H:i:s', strtotime($c['heure_debut_reelle'])) : null;
            $c['heure_fin_reelle'] = $c['heure_fin_reelle'] ? date('Y-m-d H:i:s', strtotime($c['heure_fin_reelle'])) : null;

            $rangInfo = $this->sousServiceModel->getRangDynamique((int)$c['id']);
            $c['rang_dynamique'] = $rangInfo['rang'] ?? null;
            $c['personnes_devant'] = $rangInfo['personnes_devant'] ?? null;
        }
        
        $this->jsonResponse(true, 'Consultations récupérées', [
            'consultations' => $consultations,
            'total' => count($consultations),
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * GET /index.php?action=patient_rang_consultation&consultation_id=XXX
     * Headers: Authorization: Bearer <token>
     * Endpoint léger pour rafraîchir uniquement le rang dynamique d'une
     * consultation (utile pour le polling périodique depuis le dashboard
     * mobile, sans recharger toutes les consultations).
     */
    public function getRangConsultation(): void
    {
        $patientId = $this->getAuthenticatedPatientId();

        if (!$patientId) {
            $this->jsonResponse(false, 'Non authentifié', [], 401);
            return;
        }

        $consultationId = (int)($_GET['consultation_id'] ?? 0);

        if ($consultationId <= 0) {
            $this->jsonResponse(false, 'Consultation ID requis', [], 400);
            return;
        }

        $consultation = $this->consultationModel->findById($consultationId);

        if (!$consultation || (int)$consultation['patient_id'] !== $patientId) {
            $this->jsonResponse(false, 'Consultation non trouvée', [], 404);
            return;
        }

        $rangInfo = $this->sousServiceModel->getRangDynamique($consultationId);

        $this->jsonResponse(true, 'Rang récupéré', [
            'consultation_id'  => $consultationId,
            'rang'             => $rangInfo['rang'] ?? null,
            'personnes_devant' => $rangInfo['personnes_devant'] ?? null,
            'statut'           => $rangInfo['statut'] ?? $consultation['statut'],
        ]);
    }

    /* ═══════════════════════════════════════════════════════════
       PRISE DE RENDEZ-VOUS EN LIGNE
    ═══════════════════════════════════════════════════════════ */

    /**
     * GET /index.php?action=patient_creneaux_sous_service&sous_service_id=XXX
     * Headers: Authorization: Bearer <token>
     *
     * Retourne tous les créneaux disponibles dans le sous-service choisi,
     * sur toute la semaine à venir, peu importe le médecin qui prendra
     * en charge le patient (les créneaux sont agrégés sur tous les
     * médecins affectés au sous-service).
     */
    public function getCreneauxSousService(): void
    {
        $patientId = $this->getAuthenticatedPatientId();

        if (!$patientId) {
            $this->jsonResponse(false, 'Non authentifié', [], 401);
            return;
        }

        $sousServiceId = (int)($_GET['sous_service_id'] ?? 0);

        if ($sousServiceId <= 0) {
            $this->jsonResponse(false, 'Sous-service requis', [], 400);
            return;
        }

        $sousService = $this->sousServiceModel->getById($sousServiceId);

        if (!$sousService || $sousService['statut'] !== 'actif') {
            $this->jsonResponse(false, 'Sous-service introuvable ou inactif', [], 404);
            return;
        }

        $nbJours = (int)($_GET['nb_jours'] ?? 7);
        $nbJours = max(1, min($nbJours, 14));

        $semaine = $this->sousServiceModel->getCreneauxSemaine($sousServiceId, $nbJours);

        $this->jsonResponse(true, 'Créneaux récupérés', [
            'sous_service' => [
                'id'  => $sousService['id'],
                'nom' => $sousService['nom'],
            ],
            'semaine' => $semaine,
        ]);
    }

    /**
     * POST /index.php?action=patient_reserver_consultation
     * Headers: Authorization: Bearer <token>
     * Body: {"sous_service_id": 3, "date": "2026-06-25", "heure": "09:00", "motif": "..."}
     *
     * Réserve un créneau en ligne pour le patient connecté. Le médecin qui
     * prendra en charge la consultation est attribué automatiquement parmi
     * les médecins du sous-service encore disponibles sur ce créneau.
     */
    public function reserverConsultation(): void
    {
        $patientId = $this->getAuthenticatedPatientId();

        if (!$patientId) {
            $this->jsonResponse(false, 'Non authentifié', [], 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $sousServiceId = (int)($input['sous_service_id'] ?? 0);
        $date = trim($input['date'] ?? '');
        $heure = trim($input['heure'] ?? '');
        $motif = trim($input['motif'] ?? '');

        if ($sousServiceId <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $heure)) {
            $this->jsonResponse(false, 'Sous-service, date (Y-m-d) et heure (H:i) requis', [], 400);
            return;
        }

        if ($date <= date('Y-m-d')) {
            $this->jsonResponse(false, 'La date du rendez-vous doit être dans le futur', [], 400);
            return;
        }

        $sousService = $this->sousServiceModel->getById($sousServiceId);
        if (!$sousService || $sousService['statut'] !== 'actif') {
            $this->jsonResponse(false, 'Sous-service introuvable ou inactif', [], 404);
            return;
        }

        // Vérifier que le patient n'a pas déjà un rendez-vous ce jour-là
        // dans ce sous-service (évite les doublons de réservation).
        $stmtDoublon = $this->db->prepare(
            'SELECT id FROM consultations
             WHERE patient_id = :pid AND sous_service_id = :ssid
               AND DATE(heure_passage_estimee) = :date
               AND statut NOT IN ("annule", "absent")'
        );
        $stmtDoublon->execute([':pid' => $patientId, ':ssid' => $sousServiceId, ':date' => $date]);
        if ($stmtDoublon->fetch()) {
            $this->jsonResponse(false, 'Vous avez déjà un rendez-vous ce jour-là dans ce sous-service', [], 409);
            return;
        }

        // Choix automatique du médecin disponible sur ce créneau
        $medecinId = $this->sousServiceModel->getMedecinDisponiblePourCreneau($sousServiceId, $date, $heure);

        if (!$medecinId) {
            $this->jsonResponse(false, 'Ce créneau n\'est plus disponible, veuillez en choisir un autre', [], 409);
            return;
        }

        $dureeEstimee = (int)($sousService['duree_estimee'] ?? $sousService['duree_rdv_defaut'] ?? 1800);
        $heurePassage = $date . ' ' . $heure . ':00';

        // Rang d'arrivée (ordre de création) pour ce jour/sous-service.
        // Le rang AFFICHÉ au patient restera dynamique (voir getRangDynamique) :
        // celui-ci ne sert qu'à départager l'ordre d'arrivée à rang égal.
        $rang = $this->consultationModel->getProchainRangPourDate($sousServiceId, $date);

        $consultationId = $this->consultationModel->creer([
            'patient_id'            => $patientId,
            'sous_service_id'       => $sousServiceId,
            'medecin_id'            => $medecinId,
            'statut'                => 'confirme',
            'rang'                  => $rang,
            'mode_prise'            => 'LIGNE',
            'heure_passage_estimee' => $heurePassage,
            'motif'                 => $motif !== '' ? $motif : null,
            'duree_estimee'         => $dureeEstimee,
        ]);

        if (!$consultationId) {
            $this->jsonResponse(false, 'Erreur lors de la réservation', [], 500);
            return;
        }

        $rangInfo = $this->sousServiceModel->getRangDynamique($consultationId);

        $this->jsonResponse(true, 'Rendez-vous réservé avec succès', [
            'consultation_id'  => $consultationId,
            'sous_service_id'  => $sousServiceId,
            'medecin_id'       => $medecinId,
            'date'             => $date,
            'heure'            => $heure,
            'rang'             => $rangInfo['rang'] ?? null,
            'personnes_devant' => $rangInfo['personnes_devant'] ?? null,
        ], 201);
    }

    /**
     * GET /index.php?action=patient_tickets
     * Headers: Authorization: Bearer <token>
     */
    public function getTickets(): void
    {
        $patientId = $this->getAuthenticatedPatientId();
        
        if (!$patientId) {
            $this->jsonResponse(false, 'Non authentifié', [], 401);
            return;
        }
        
        $tickets = $this->patientModel->getTicketsPatient($patientId);
        
        $this->jsonResponse(true, 'Tickets récupérés', [
            'tickets' => $tickets,
            'total' => count($tickets)
        ]);
    }

    /**
     * GET /index.php?action=patient_notifications
     * Headers: Authorization: Bearer <token>
     */
    public function getNotifications(): void
    {
        $patientId = $this->getAuthenticatedPatientId();
        
        if (!$patientId) {
            $this->jsonResponse(false, 'Non authentifié', [], 401);
            return;
        }
        
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        $notifications = $this->notificationModel->getNotificationsPatient($patientId, $limit, $offset);
        $unreadCount = $this->notificationModel->compterNonLues($patientId);
        
        $this->jsonResponse(true, 'Notifications récupérées', [
            'notifications' => $notifications,
            'unread_count' => $unreadCount,
            'total' => count($notifications),
            'limit' => $limit,
            'offset' => $offset
        ]);
    }

    /**
     * POST /index.php?action=mark_notification_read&notification_id=XXX
     * Headers: Authorization: Bearer <token>
     */
    public function markNotificationRead(): void
    {
        $patientId = $this->getAuthenticatedPatientId();
        $notificationId = (int)($_GET['notification_id'] ?? 0);
        
        if (!$patientId) {
            $this->jsonResponse(false, 'Non authentifié', [], 401);
            return;
        }
        
        if ($notificationId <= 0) {
            $this->jsonResponse(false, 'Notification ID requis', [], 400);
            return;
        }
        
        $notification = $this->notificationModel->trouverParId($notificationId);
        
        if (!$notification || $notification['patient_id'] != $patientId) {
            $this->jsonResponse(false, 'Notification non trouvée', [], 404);
            return;
        }
        
        $success = $this->notificationModel->mettreAJourStatut($notificationId, 'lu');
        
        if (!$success) {
            $this->jsonResponse(false, 'Erreur lors de la mise à jour', [], 500);
            return;
        }
        
        $this->jsonResponse(true, 'Notification marquée comme lue');
    }

    /**
     * POST /index.php?action=mark_all_notifications_read
     * Headers: Authorization: Bearer <token>
     */
    public function markAllNotificationsRead(): void
    {
        $patientId = $this->getAuthenticatedPatientId();
        
        if (!$patientId) {
            $this->jsonResponse(false, 'Non authentifié', [], 401);
            return;
        }
        
        $count = $this->notificationModel->marquerToutCommeLu($patientId);
        
        $this->jsonResponse(true, 'Toutes les notifications ont été marquées comme lues', [
            'marked_count' => $count
        ]);
    }

    /* ═══════════════════════════════════════════════════════════
       MOT DE PASSE
    ═══════════════════════════════════════════════════════════ */

    /**
     * POST /index.php?action=patient_change_password
     * Headers: Authorization: Bearer <token>
     * Body: {"current_password": "xxx", "new_password": "xxx"}
     */
    public function changePassword(): void
    {
        $patientId = $this->getAuthenticatedPatientId();
        
        if (!$patientId) {
            $this->jsonResponse(false, 'Non authentifié', [], 401);
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        $currentPassword = $input['current_password'] ?? '';
        $newPassword = $input['new_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword)) {
            $this->jsonResponse(false, 'Mot de passe actuel et nouveau mot de passe requis', [], 400);
            return;
        }
        
        if (strlen($newPassword) < 6) {
            $this->jsonResponse(false, 'Le nouveau mot de passe doit contenir au moins 6 caractères', [], 400);
            return;
        }
        
        $patient = $this->patientModel->trouverParId($patientId);
        
        if (!password_verify($currentPassword, $patient['password'])) {
            $this->jsonResponse(false, 'Mot de passe actuel incorrect', [], 401);
            return;
        }
        
        $success = $this->patientModel->mettreAJourMotDePasse($patientId, $newPassword);
        
        if (!$success) {
            $this->jsonResponse(false, 'Erreur lors du changement de mot de passe', [], 500);
            return;
        }
        
        $this->jsonResponse(true, 'Mot de passe modifié avec succès');
    }

    /**
     * POST /index.php?action=patient_reset_password
     * Body: {"email": "xxx"}
     * Envoie un email de réinitialisation (à implémenter)
     */
    public function resetPassword(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        $email = trim($input['email'] ?? '');
        
        if (empty($email)) {
            $this->jsonResponse(false, 'Email requis', [], 400);
            return;
        }
        
        $patient = $this->patientModel->trouverParEmail($email);
        
        if (!$patient) {
            // Pour des raisons de sécurité, on ne révèle pas si l'email existe
            $this->jsonResponse(true, 'Si votre email est enregistré, vous recevrez un lien de réinitialisation');
            return;
        }
        
        // TODO: Implémenter l'envoi d'email de réinitialisation
        // Générer un token de réinitialisation
        $resetToken = bin2hex(random_bytes(32));
        $expireAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $this->db->prepare(
            'INSERT INTO password_reset_tokens (patient_id, token, expire_at) 
             VALUES (:pid, :token, :expire_at)
             ON DUPLICATE KEY UPDATE token = :token, expire_at = :expire_at'
        );
        $stmt->execute([
            ':pid' => $patient['id'],
            ':token' => $resetToken,
            ':expire_at' => $expireAt
        ]);
        
        // TODO: Envoyer email avec lien: https://votre-site.com/reset-password?token=$resetToken
        
        $this->jsonResponse(true, 'Si votre email est enregistré, vous recevrez un lien de réinitialisation');
    }
}