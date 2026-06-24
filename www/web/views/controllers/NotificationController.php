<?php
/**
 * controllers/NotificationController.php
 * Contrôleur pour la gestion des notifications push
 */

require_once __DIR__ . '/../models/PatientModel.php';
require_once __DIR__ . '/../models/ConsultationModel.php';
require_once __DIR__ . '/../helpers/NotificationHelper.php';

class NotificationController
{
    private PatientModel $patientModel;
    private ConsultationModel $consultationModel;

    public function __construct()
    {
        $this->patientModel = new PatientModel();
        $this->consultationModel = new ConsultationModel();
    }

    /* ═══════════════════════════════════════════════════════════
       GESTION DES TOKENS FCM
    ═══════════════════════════════════════════════════════════ */

    /**
     * Sauvegarde le token FCM reçu du client
     * URL: index.php?action=save_fcm_token
     */
    public function saveFCMToken(): void
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['token'] ?? '';
        
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Token manquant']);
            exit;
        }
        
        // Si le patient est connecté, associer le token à son compte
        if (isset($_SESSION['patient_id'])) {
            $patientId = (int)$_SESSION['patient_id'];
            $success = $this->patientModel->mettreAJourTokenFCM($patientId, $token);
            
            if ($success) {
                // Envoyer une notification de bienvenue
                $patient = $this->patientModel->trouverParId($patientId);
                if ($patient) {
                    $helper = NotificationHelper::getInstance();
                    $helper->notifyWelcome($token, $patient);
                }
                echo json_encode(['success' => true, 'message' => 'Token enregistré']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'enregistrement']);
            }
        } else {
            // Stocker temporairement en session
            $_SESSION['temp_fcm_token'] = $token;
            echo json_encode(['success' => true, 'message' => 'Token stocké temporairement', 'needs_login' => true]);
        }
        exit;
    }

    /**
     * Supprime le token FCM (désabonnement)
     * URL: index.php?action=remove_fcm_token
     */
    public function removeFCMToken(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['patient_id'])) {
            echo json_encode(['success' => false, 'message' => 'Non authentifié']);
            exit;
        }
        
        $patientId = (int)$_SESSION['patient_id'];
        $success = $this->patientModel->supprimerTokenFCM($patientId);
        
        echo json_encode([
            'success' => $success,
            'message' => $success ? 'Token supprimé' : 'Erreur lors de la suppression'
        ]);
        exit;
    }

    /**
     * Associe un token temporaire au patient après connexion
     */
    public function associateTempToken(): void
    {
        if (isset($_SESSION['temp_fcm_token']) && isset($_SESSION['patient_id'])) {
            $token = $_SESSION['temp_fcm_token'];
            $patientId = (int)$_SESSION['patient_id'];
            
            $this->patientModel->mettreAJourTokenFCM($patientId, $token);
            unset($_SESSION['temp_fcm_token']);
            
            // Envoyer notification de bienvenue
            $patient = $this->patientModel->trouverParId($patientId);
            if ($patient && $token) {
                $helper = NotificationHelper::getInstance();
                $helper->notifyWelcome($token, $patient);
            }
        }
    }

    /* ═══════════════════════════════════════════════════════════
       ENVOI DE NOTIFICATIONS SPÉCIFIQUES
    ═══════════════════════════════════════════════════════════ */

    /**
     * Envoie une notification de rappel pour une consultation
     * URL: index.php?action=send_reminder&consultation_id=X
     */
    public function sendConsultationReminder(int $consultationId): array
    {
        $consultation = $this->consultationModel->findWithDetails($consultationId);
        
        if (!$consultation) {
            return ['success' => false, 'message' => 'Consultation introuvable'];
        }
        
        $token = $this->patientModel->getTokenFCM($consultation['patient_id']);
        
        if (!$token) {
            return ['success' => false, 'message' => 'Patient non abonné aux notifications'];
        }
        
        $helper = NotificationHelper::getInstance();
        return $helper->notifyConsultationReminder($token, $consultation);
    }

    /**
     * Envoie une notification au patient appelé
     * URL: index.php?action=notify_called&consultation_id=X
     */
    public function sendPatientCalled(int $consultationId): array
    {
        $consultation = $this->consultationModel->findWithDetails($consultationId);
        
        if (!$consultation) {
            return ['success' => false, 'message' => 'Consultation introuvable'];
        }
        
        $token = $this->patientModel->getTokenFCM($consultation['patient_id']);
        
        if (!$token) {
            return ['success' => false, 'message' => 'Patient non abonné aux notifications'];
        }
        
        $helper = NotificationHelper::getInstance();
        return $helper->notifyPatientCalled($token, $consultation);
    }

    /**
     * Envoie une notification de confirmation de rendez-vous
     * URL: index.php?action=confirm_appointment&consultation_id=X
     */
    public function sendAppointmentConfirmed(int $consultationId): array
    {
        $consultation = $this->consultationModel->findWithDetails($consultationId);
        
        if (!$consultation) {
            return ['success' => false, 'message' => 'Consultation introuvable'];
        }
        
        $token = $this->patientModel->getTokenFCM($consultation['patient_id']);
        
        if (!$token) {
            return ['success' => false, 'message' => 'Patient non abonné aux notifications'];
        }
        
        $helper = NotificationHelper::getInstance();
        return $helper->notifyAppointmentConfirmed($token, $consultation);
    }

    /**
     * Envoie une notification d'annulation
     * URL: index.php?action=cancel_appointment&consultation_id=X&reason=...
     */
    public function sendCancellation(int $consultationId, string $reason = ''): array
    {
        $consultation = $this->consultationModel->findWithDetails($consultationId);
        
        if (!$consultation) {
            return ['success' => false, 'message' => 'Consultation introuvable'];
        }
        
        $token = $this->patientModel->getTokenFCM($consultation['patient_id']);
        
        if (!$token) {
            return ['success' => false, 'message' => 'Patient non abonné aux notifications'];
        }
        
        $helper = NotificationHelper::getInstance();
        return $helper->notifyCancellation($token, $consultation, $reason);
    }

    /**
     * Envoie une notification de modification de rendez-vous
     * URL: index.php?action=update_appointment&consultation_id=X
     */
    public function sendAppointmentUpdated(int $consultationId, string $oldDate = '', string $oldTime = ''): array
    {
        $consultation = $this->consultationModel->findWithDetails($consultationId);
        
        if (!$consultation) {
            return ['success' => false, 'message' => 'Consultation introuvable'];
        }
        
        $token = $this->patientModel->getTokenFCM($consultation['patient_id']);
        
        if (!$token) {
            return ['success' => false, 'message' => 'Patient non abonné aux notifications'];
        }
        
        $helper = NotificationHelper::getInstance();
        return $helper->notifyAppointmentUpdated($token, $consultation, $oldDate, $oldTime);
    }

    /**
     * Test d'envoi de notification
     * URL: index.php?action=test_notification (POST avec {"token": "xxx"})
     */
    public function testNotification(): void
    {
        header('Content-Type: application/json');
        
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['token'] ?? '';
        
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Token manquant']);
            exit;
        }
        
        $helper = NotificationHelper::getInstance();
        $result = $helper->sendTestNotification($token);
        
        echo json_encode($result);
        exit;
    }

    /* ═══════════════════════════════════════════════════════════
       ENVOIS GROUPÉS (CRON JOBS)
    ═══════════════════════════════════════════════════════════ */

    /**
     * Envoie des rappels pour toutes les consultations du lendemain
     * À appeler via cron job tous les jours à 18h
     * URL: index.php?action=send_daily_reminders
     */
    public function sendDailyReminders(): void
    {
        header('Content-Type: application/json');
        
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        // Récupérer toutes les consultations de demain avec les détails
        $consultations = $this->consultationModel->getConsultationsByDate($tomorrow);
        
        $helper = NotificationHelper::getInstance();
        $result = $helper->sendBulkDayBeforeReminders($consultations, function($patientId) {
            return $this->patientModel->getTokenFCM($patientId);
        });
        
        // Log du résultat
        error_log("[CRON] Rappels envoyés: {$result['sent']} succès, {$result['failed']} échecs");
        
        echo json_encode($result);
        exit;
    }

    /**
     * Envoie une notification à tous les patients abonnés
     * URL: index.php?action=broadcast&title=XXX&body=XXX
     */
    public function broadcast(string $title, string $body, array $data = []): array
    {
        $tokens = $this->patientModel->getAllActiveTokens();
        
        if (empty($tokens)) {
            return ['success' => false, 'message' => 'Aucun patient abonné'];
        }
        
        $helper = NotificationHelper::getInstance();
        return $helper->sendToMultipleDevices($tokens, $title, $body, $data);
    }

    /* ═══════════════════════════════════════════════════════════
       VÉRIFICATIONS ET STATUT
    ═══════════════════════════════════════════════════════════ */

    /**
     * Vérifie le statut des notifications pour le patient connecté
     * URL: index.php?action=notification_status
     */
    public function getNotificationStatus(): void
    {
        header('Content-Type: application/json');
        
        if (!isset($_SESSION['patient_id'])) {
            echo json_encode([
                'success' => true,
                'has_token' => false,
                'message' => 'Non connecté'
            ]);
            exit;
        }
        
        $patientId = (int)$_SESSION['patient_id'];
        $token = $this->patientModel->getTokenFCM($patientId);
        
        echo json_encode([
            'success' => true,
            'has_token' => !empty($token),
            'token' => $token ? 'présent' : 'absent'
        ]);
        exit;
    }

    /**
     * Point d'entrée AJAX pour les requêtes
     */
    public function handleRequest(): void
    {
        $action = $_GET['action'] ?? '';
        
        switch ($action) {
            case 'save_fcm_token':
                $this->saveFCMToken();
                break;
            case 'remove_fcm_token':
                $this->removeFCMToken();
                break;
            case 'test_notification':
                $this->testNotification();
                break;
            case 'notification_status':
                $this->getNotificationStatus();
                break;
            case 'send_reminder':
                $consultationId = (int)($_GET['consultation_id'] ?? 0);
                $result = $this->sendConsultationReminder($consultationId);
                echo json_encode($result);
                break;
            case 'notify_called':
                $consultationId = (int)($_GET['consultation_id'] ?? 0);
                $result = $this->sendPatientCalled($consultationId);
                echo json_encode($result);
                break;
            case 'send_daily_reminders':
                $this->sendDailyReminders();
                break;
            default:
                echo json_encode(['success' => false, 'message' => 'Action non reconnue']);
                break;
        }
        exit;
    }
}

// Point d'entrée pour les requêtes AJAX
if (basename($_SERVER['SCRIPT_FILENAME']) === 'NotificationController.php' && php_sapi_name() !== 'cli') {
    $ctrl = new NotificationController();
    $ctrl->handleRequest();
}