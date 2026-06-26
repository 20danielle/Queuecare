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
require_once __DIR__ . '/../config/database.php';

class PatientController
{
    private PDO $db;
    private PatientModel $patientModel;
    private ConsultationModel $consultationModel;
    private NotificationModel $notificationModel;
    private TicketModel $ticketModel;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->patientModel = new PatientModel();
        $this->consultationModel = new ConsultationModel();
        $this->notificationModel = new NotificationModel();
        $this->ticketModel = new TicketModel();
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
        
        // Formater les dates
        foreach ($consultations as &$c) {
            $c['heure_passage_estimee'] = $c['heure_passage_estimee'] ? date('Y-m-d H:i:s', strtotime($c['heure_passage_estimee'])) : null;
            $c['heure_debut_reelle'] = $c['heure_debut_reelle'] ? date('Y-m-d H:i:s', strtotime($c['heure_debut_reelle'])) : null;
            $c['heure_fin_reelle'] = $c['heure_fin_reelle'] ? date('Y-m-d H:i:s', strtotime($c['heure_fin_reelle'])) : null;
        }
        
        $this->jsonResponse(true, 'Consultations récupérées', [
            'consultations' => $consultations,
            'total' => count($consultations),
            'limit' => $limit,
            'offset' => $offset
        ]);
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