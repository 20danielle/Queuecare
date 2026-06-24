<?php
/**
 * models/NotificationModel.php
 * Modèle pour la gestion des notifications en base de données
 * Permet de tracer l'historique des notifications envoyées
 */

require_once __DIR__ . '/../config/database.php';

class NotificationModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /* ═══════════════════════════════════════════════════════════
       CRUD NOTIFICATIONS
    ═══════════════════════════════════════════════════════════ */

    /**
     * Crée une notification dans la base de données
     */
    public function creer(array $data): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications (patient_id, consultation_id, type, contenu, canal, statut, sent_at, created_at)
             VALUES (:patient_id, :consultation_id, :type, :contenu, :canal, :statut, :sent_at, NOW())'
        );
        
        $success = $stmt->execute([
            ':patient_id'       => $data['patient_id'] ?? null,
            ':consultation_id'  => $data['consultation_id'] ?? null,
            ':type'             => $data['type'] ?? 'INFO',
            ':contenu'          => $data['contenu'] ?? '',
            ':canal'            => $data['canal'] ?? 'FCM',
            ':statut'           => $data['statut'] ?? 'en_attente',
            ':sent_at'          => $data['sent_at'] ?? null
        ]);
        
        return $success ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Met à jour le statut d'une notification
     */
    public function mettreAJourStatut(int $id, string $statut, ?string $sentAt = null): bool
    {
        $allowed = ['en_attente', 'envoye', 'echec', 'lu'];
        if (!in_array($statut, $allowed)) {
            return false;
        }
        
        if ($sentAt === null && $statut === 'envoye') {
            $sentAt = date('Y-m-d H:i:s');
        }
        
        $stmt = $this->db->prepare(
            'UPDATE notifications SET statut = :statut, sent_at = COALESCE(:sent_at, sent_at) WHERE id = :id'
        );
        return $stmt->execute([
            ':statut' => $statut,
            ':sent_at' => $sentAt,
            ':id' => $id
        ]);
    }

    /**
     * Marque une notification comme lue
     */
    public function marquerCommeLu(int $id): bool
    {
        return $this->mettreAJourStatut($id, 'lu');
    }

    /**
     * Marque toutes les notifications d'un patient comme lues
     */
    public function marquerToutCommeLu(int $patientId): int
    {
        $stmt = $this->db->prepare(
            'UPDATE notifications SET statut = "lu" 
             WHERE patient_id = :pid AND statut != "lu"'
        );
        $stmt->execute([':pid' => $patientId]);
        return $stmt->rowCount();
    }

    /* ═══════════════════════════════════════════════════════════
       RÉCUPÉRATION DES NOTIFICATIONS
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère une notification par son ID
     */
    public function trouverParId(int $id)
    {
        $stmt = $this->db->prepare(
            'SELECT n.*, p.nom as patient_nom, p.prenom as patient_prenom
             FROM notifications n
             LEFT JOIN patients p ON p.id = n.patient_id
             WHERE n.id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Récupère toutes les notifications d'un patient
     */
    public function getNotificationsPatient(int $patientId, int $limit = 50, int $offset = 0): array
    {
        $stmt = $this->db->prepare(
            'SELECT n.*, c.sous_service_id, ss.nom as sous_service_nom
             FROM notifications n
             LEFT JOIN consultations c ON c.id = n.consultation_id
             LEFT JOIN sous_services ss ON ss.id = c.sous_service_id
             WHERE n.patient_id = :pid
             ORDER BY n.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':pid', $patientId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Récupère les notifications non lues d'un patient
     */
    public function getNotificationsNonLues(int $patientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT n.*, c.sous_service_id, ss.nom as sous_service_nom
             FROM notifications n
             LEFT JOIN consultations c ON c.id = n.consultation_id
             LEFT JOIN sous_services ss ON ss.id = c.sous_service_id
             WHERE n.patient_id = :pid AND n.statut != "lu"
             ORDER BY n.created_at DESC'
        );
        $stmt->execute([':pid' => $patientId]);
        return $stmt->fetchAll();
    }

    /**
     * Compte les notifications non lues d'un patient
     */
    public function compterNonLues(int $patientId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM notifications 
             WHERE patient_id = :pid AND statut != "lu"'
        );
        $stmt->execute([':pid' => $patientId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Récupère les notifications par consultation
     */
    public function getNotificationsParConsultation(int $consultationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM notifications 
             WHERE consultation_id = :cid
             ORDER BY created_at DESC'
        );
        $stmt->execute([':cid' => $consultationId]);
        return $stmt->fetchAll();
    }

    /**
     * Récupère les notifications par type
     */
    public function getNotificationsParType(string $type, int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT n.*, p.nom as patient_nom, p.prenom as patient_prenom
             FROM notifications n
             LEFT JOIN patients p ON p.id = n.patient_id
             WHERE n.type = :type
             ORDER BY n.created_at DESC
             LIMIT :limit'
        );
        $stmt->bindValue(':type', $type, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Récupère les notifications en attente d'envoi
     */
    public function getNotificationsEnAttente(int $limit = 100): array
    {
        $stmt = $this->db->prepare(
            'SELECT n.*, p.nom as patient_nom, p.prenom as patient_prenom, p.token_fcm
             FROM notifications n
             JOIN patients p ON p.id = n.patient_id
             WHERE n.statut = "en_attente"
               AND p.token_fcm IS NOT NULL
               AND p.token_fcm != ""
             ORDER BY n.created_at ASC
             LIMIT :limit'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /* ═══════════════════════════════════════════════════════════
       STATISTIQUES
    ═══════════════════════════════════════════════════════════ */

    /**
     * Statistiques globales des notifications
     */
    public function getStats(): array
    {
        $stats = [];
        
        // Total des notifications
        $stmt = $this->db->query('SELECT COUNT(*) as total FROM notifications');
        $stats['total'] = (int)$stmt->fetchColumn();
        
        // Par statut
        $stmt = $this->db->query(
            'SELECT statut, COUNT(*) as count 
             FROM notifications 
             GROUP BY statut'
        );
        $stats['par_statut'] = [];
        while ($row = $stmt->fetch()) {
            $stats['par_statut'][$row['statut']] = (int)$row['count'];
        }
        
        // Par type
        $stmt = $this->db->query(
            'SELECT type, COUNT(*) as count 
             FROM notifications 
             GROUP BY type 
             ORDER BY count DESC 
             LIMIT 10'
        );
        $stats['par_type'] = $stmt->fetchAll();
        
        // Aujourd'hui
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as count 
             FROM notifications 
             WHERE DATE(created_at) = CURDATE()'
        );
        $stmt->execute();
        $stats['aujourdhui'] = (int)$stmt->fetchColumn();
        
        // 7 derniers jours
        $stmt = $this->db->prepare(
            'SELECT DATE(created_at) as date, COUNT(*) as count 
             FROM notifications 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date ASC'
        );
        $stmt->execute();
        $stats['7_derniers_jours'] = $stmt->fetchAll();
        
        return $stats;
    }

    /**
     * Statistiques par patient
     */
    public function getStatsPatient(int $patientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT 
                COUNT(*) as total,
                SUM(statut = "envoye") as envoyees,
                SUM(statut = "lu") as lues,
                SUM(statut = "echec") as echecs,
                SUM(statut = "en_attente") as en_attente
             FROM notifications 
             WHERE patient_id = :pid'
        );
        $stmt->execute([':pid' => $patientId]);
        return $stmt->fetch() ?: [];
    }

    /* ═══════════════════════════════════════════════════════════
       NETTOYAGE ET MAINTENANCE
    ═══════════════════════════════════════════════════════════ */

    /**
     * Supprime les notifications anciennes
     */
    public function nettoyerAnciennes(int $jours = 90): int
    {
        $stmt = $this->db->prepare(
            'DELETE FROM notifications 
             WHERE created_at < DATE_SUB(NOW(), INTERVAL :jours DAY)
             AND statut IN ("envoye", "lu", "echec")'
        );
        $stmt->bindValue(':jours', $jours, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Supprime les notifications d'un patient
     */
    public function supprimerNotificationsPatient(int $patientId): int
    {
        $stmt = $this->db->prepare('DELETE FROM notifications WHERE patient_id = :pid');
        $stmt->execute([':pid' => $patientId]);
        return $stmt->rowCount();
    }

    /* ═══════════════════════════════════════════════════════════
       MÉTHODES UTILITAIRES POUR L'ENVOI
    ═══════════════════════════════════════════════════════════ */

    /**
     * Enregistre une notification et l'envoie immédiatement
     */
    public function enregistrerEtEnvoyer(
        int $patientId, 
        string $type, 
        string $contenu, 
        ?int $consultationId = null,
        ?string $token = null
    ): array {
        // Créer la notification en base
        $notificationId = $this->creer([
            'patient_id'      => $patientId,
            'consultation_id' => $consultationId,
            'type'            => $type,
            'contenu'         => $contenu,
            'canal'           => 'FCM',
            'statut'          => 'en_attente'
        ]);
        
        if (!$notificationId) {
            return ['success' => false, 'message' => 'Erreur lors de la création de la notification'];
        }
        
        // Récupérer le token si non fourni
        if ($token === null) {
            require_once __DIR__ . '/PatientModel.php';
            $patientModel = new PatientModel();
            $token = $patientModel->getTokenFCM($patientId);
        }
        
        if (!$token) {
            $this->mettreAJourStatut($notificationId, 'echec');
            return ['success' => false, 'message' => 'Patient non abonné aux notifications'];
        }
        
        // Envoyer la notification
        require_once __DIR__ . '/../helpers/NotificationHelper.php';
        $helper = NotificationHelper::getInstance();
        
        // Déterminer le titre en fonction du type
        $title = $this->getTitleForType($type);
        
        $result = $helper->sendToDevice($token, $title, $contenu, [
            'type' => $type,
            'consultation_id' => $consultationId,
            'notification_id' => $notificationId
        ]);
        
        // Mettre à jour le statut
        if ($result['success']) {
            $this->mettreAJourStatut($notificationId, 'envoye');
        } else {
            $this->mettreAJourStatut($notificationId, 'echec');
        }
        
        return [
            'success' => $result['success'],
            'notification_id' => $notificationId,
            'message' => $result['message']
        ];
    }

    /**
     * Détermine le titre d'une notification selon son type
     */
    private function getTitleForType(string $type): string
    {
        $titles = [
            'CONFIRMATION' => '✅ Rendez-vous confirmé',
            'RAPPEL_J1' => '📅 Rappel consultation',
            'RAPPEL_15MIN' => '⏰ Rappel immédiat',
            'APPEL_IMMEDIAT' => '🔔 Vous êtes appelé(e) !',
            'AVANCEMENT' => '📊 Avancement file d\'attente',
            'DECALAGE' => '⚠️ Horaire modifié',
            'ANNULATION' => '❌ Consultation annulée',
            'CLOTURE_ABSENT' => '⚠️ Absence constatée',
            'MAJ_HEURE' => '🕐 Horaire mis à jour',
            'URGENCE' => '🚨 Information importante',
            'INFO' => 'ℹ️ Information',
            'WELCOME' => '👋 Bienvenue sur QueueCare',
            'TEST' => '🔔 Test notification'
        ];
        
        return $titles[$type] ?? '📱 QueueCare';
    }

    /**
     * Types de notifications disponibles
     */
    public static function getTypesDisponibles(): array
    {
        return [
            'CONFIRMATION' => 'Confirmation de rendez-vous',
            'RAPPEL_J1' => 'Rappel à J-1',
            'RAPPEL_15MIN' => 'Rappel 15 minutes avant',
            'APPEL_IMMEDIAT' => 'Appel immédiat du patient',
            'AVANCEMENT' => 'Avancement de la file',
            'DECALAGE' => 'Décalage d\'horaire',
            'ANNULATION' => 'Annulation de consultation',
            'CLOTURE_ABSENT' => 'Clôture pour absence',
            'MAJ_HEURE' => 'Mise à jour horaire',
            'URGENCE' => 'Urgence / Information',
            'INFO' => 'Information générale',
            'WELCOME' => 'Bienvenue',
            'TEST' => 'Test'
        ];
    }
}