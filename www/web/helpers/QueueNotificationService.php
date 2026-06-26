<?php
/**
 * helpers/QueueNotificationService.php
 *
 * Service centralisé pour toutes les notifications push liées à la file
 * d'attente. Chaque événement métier (consultation terminée, patient absent,
 * approche du tour, urgence, etc.) déclenche ici les envois FCM appropriés
 * et persiste une trace en base via NotificationModel.
 *
 * Usage :
 *   $svc = new QueueNotificationService();
 *   $svc->onConsultationTerminee($consultationId, $ssId);
 *   $svc->onPatientAbsent($consultationId, $ssId);
 *   $svc->onRappel30Min($consultationId);
 *   // …
 */

require_once __DIR__ . '/NotificationHelper.php';
require_once __DIR__ . '/../models/NotificationModel.php';
require_once __DIR__ . '/../models/PatientModel.php';
require_once __DIR__ . '/../config/database.php';

class QueueNotificationService
{
    private NotificationHelper  $helper;
    private NotificationModel   $notifModel;
    private PatientModel        $patientModel;
    private PDO                 $db;

    public function __construct()
    {
        $this->helper       = NotificationHelper::getInstance();
        $this->notifModel   = new NotificationModel();
        $this->patientModel = new PatientModel();
        $this->db           = Database::getInstance()->getConnection();
    }

    /* ═══════════════════════════════════════════════════════════════════
       1. CONSULTATION TERMINÉE
       → Notifie TOUS les patients restant dans la file que leur rang a
         avancé d'une position.
    ═══════════════════════════════════════════════════════════════════ */

    /**
     * À appeler juste après qu'une consultation passe en statut "traite".
     *
     * @param int $termineeConsultationId  ID de la consultation qui vient de se terminer.
     * @param int $ssId                    ID du sous-service concerné.
     */
    public function onConsultationTerminee(int $termineeConsultationId, int $ssId): void
    {
        if (!$this->helper->isConfigured()) return;

        // Récupérer les détails de la consultation terminée pour contextualiser
        $terminee = $this->getConsultationDetails($termineeConsultationId);

        // Récupérer les patients encore en attente, ordonnés par rang
        $file = $this->getPatientsEnAttente($ssId);

        foreach ($file as $patient) {
            $token = $this->patientModel->getTokenFCM((int)$patient['patient_id']);
            if (!$token) continue;

            $rang       = (int)$patient['rang'];
            $prenom     = $patient['patient_prenom'] ?? 'Patient';
            $sousService = $terminee['sous_service_nom'] ?? 'consultation';

            if ($rang === 1) {
                // Le prochain — il est sur le point d'être appelé
                $titre  = '🔔 Vous êtes le prochain !';
                $corps  = "C'est bientôt votre tour en {$sousService}. Tenez-vous prêt(e).";
                $type   = 'RANG_SUIVANT';
            } else {
                // Les autres — on leur indique leur nouveau rang
                $titre  = '📊 File d\'attente avancée';
                $corps  = "Vous êtes maintenant en position {$rang} dans la file de {$sousService}.";
                $type   = 'AVANCEMENT';
            }

            $data = [
                'type'            => $type,
                'rang'            => (string)$rang,
                'consultation_id' => (string)$patient['id'],
                'url'             => '/patient/dashboard.php',
            ];

            $result = $this->helper->sendToDevice($token, $titre, $corps, $data);
            $this->persisterNotification(
                (int)$patient['patient_id'],
                (int)$patient['id'],
                $type,
                $corps,
                $result['success']
            );
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
       2. PATIENT ABSENT
       → Notifie le patient absent ET tous les autres patients de la file
         que leur rang a progressé.
    ═══════════════════════════════════════════════════════════════════ */

    /**
     * À appeler juste après qu'un patient passe en statut "absent".
     *
     * @param int $absentConsultationId  ID de la consultation marquée absente.
     * @param int $ssId                  ID du sous-service.
     */
    public function onPatientAbsent(int $absentConsultationId, int $ssId): void
    {
        if (!$this->helper->isConfigured()) return;

        $absentConsult = $this->getConsultationDetails($absentConsultationId);
        if (!$absentConsult) return;

        // 1 — Notifier le patient lui-même (au cas où il aurait encore un token valide)
        $tokenAbsent = $this->patientModel->getTokenFCM((int)$absentConsult['patient_id']);
        if ($tokenAbsent) {
            $sousService = $absentConsult['sous_service_nom'] ?? 'consultation';
            $corps   = "Vous avez été marqué(e) absent(e) pour votre consultation en {$sousService}. "
                     . "Contactez l'accueil si vous êtes présent(e).";
            $result  = $this->helper->sendToDevice(
                $tokenAbsent,
                '⚠️ Absence constatée',
                $corps,
                [
                    'type'            => 'CLOTURE_ABSENT',
                    'consultation_id' => (string)$absentConsultationId,
                    'url'             => '/patient/dashboard.php',
                ]
            );
            $this->persisterNotification(
                (int)$absentConsult['patient_id'],
                $absentConsultationId,
                'CLOTURE_ABSENT',
                $corps,
                $result['success']
            );
        }

        // 2 — Notifier les autres patients de la file (rang mis à jour)
        $this->onConsultationTerminee($absentConsultationId, $ssId);
    }

    /* ═══════════════════════════════════════════════════════════════════
       3. RAPPEL 30 MINUTES
       → Notifie un patient spécifique que son tour approche dans ~30 min.
    ═══════════════════════════════════════════════════════════════════ */

    /**
     * À appeler via un cron ou un déclencheur lorsque le temps d'attente
     * estimé d'un patient descend à ≤ 30 minutes.
     *
     * @param int $consultationId
     */
    public function onRappel30Min(int $consultationId): void
    {
        if (!$this->helper->isConfigured()) return;

        $consult = $this->getConsultationDetails($consultationId);
        if (!$consult) return;

        // Ne pas envoyer si déjà notifié pour ce rappel dans les 60 dernières min
        if ($this->dejaNotifieType($consultationId, 'RAPPEL_30MIN', 60)) return;

        $token = $this->patientModel->getTokenFCM((int)$consult['patient_id']);
        if (!$token) return;

        $sousService = $consult['sous_service_nom'] ?? 'consultation';
        $corps = "Votre passage en {$sousService} est prévu dans moins de 30 minutes. "
               . "Veuillez vous rapprocher du service dès maintenant.";

        $result = $this->helper->sendToDevice(
            $token,
            '⏳ Rapprochez-vous du service',
            $corps,
            [
                'type'            => 'RAPPEL_30MIN',
                'rang'            => (string)($consult['rang'] ?? ''),
                'consultation_id' => (string)$consultationId,
                'url'             => '/patient/dashboard.php',
            ]
        );

        $this->persisterNotification(
            (int)$consult['patient_id'],
            $consultationId,
            'RAPPEL_30MIN',
            $corps,
            $result['success']
        );
    }

    /* ═══════════════════════════════════════════════════════════════════
       4. PATIENT APPELÉ (c'est maintenant son tour)
       → Notifie le patient en tête de file que le médecin est prêt.
    ═══════════════════════════════════════════════════════════════════ */

    public function onPatientAppele(int $consultationId): void
    {
        if (!$this->helper->isConfigured()) return;

        $consult = $this->getConsultationDetails($consultationId);
        if (!$consult) return;

        $token = $this->patientModel->getTokenFCM((int)$consult['patient_id']);
        if (!$token) return;

        $medecin     = trim(($consult['medecin_prenom'] ?? '') . ' ' . ($consult['medecin_nom'] ?? ''));
        $sousService = $consult['sous_service_nom'] ?? 'consultation';
        $corps       = "Dr {$medecin} est disponible. Présentez-vous immédiatement au cabinet de {$sousService}.";

        $result = $this->helper->sendToDevice(
            $token,
            '🔔 C\'est votre tour !',
            $corps,
            [
                'type'            => 'APPEL_IMMEDIAT',
                'consultation_id' => (string)$consultationId,
                'url'             => '/patient/dashboard.php',
            ]
        );

        $this->persisterNotification(
            (int)$consult['patient_id'],
            $consultationId,
            'APPEL_IMMEDIAT',
            $corps,
            $result['success']
        );
    }

    /* ═══════════════════════════════════════════════════════════════════
       5. CONSULTATION ANNULÉE PAR LE GESTIONNAIRE / MÉDECIN
       → Notifie le patient concerné + avancement de la file.
    ═══════════════════════════════════════════════════════════════════ */

    public function onConsultationAnnulee(int $consultationId, int $ssId, string $motif = ''): void
    {
        if (!$this->helper->isConfigured()) return;

        $consult = $this->getConsultationDetails($consultationId);
        if (!$consult) return;

        $token = $this->patientModel->getTokenFCM((int)$consult['patient_id']);
        if ($token) {
            $sousService = $consult['sous_service_nom'] ?? 'consultation';
            $corps = "Votre consultation en {$sousService} a été annulée.";
            if ($motif) $corps .= " Motif : {$motif}";
            $corps .= " Veuillez vous rendre à l'accueil pour plus d'informations.";

            $result = $this->helper->sendToDevice(
                $token,
                '❌ Consultation annulée',
                $corps,
                [
                    'type'            => 'ANNULATION',
                    'consultation_id' => (string)$consultationId,
                    'url'             => '/patient/dashboard.php',
                ]
            );
            $this->persisterNotification(
                (int)$consult['patient_id'],
                $consultationId,
                'ANNULATION',
                $corps,
                $result['success']
            );
        }

        // Notifier l'avancement de la file
        $this->onConsultationTerminee($consultationId, $ssId);
    }

    /* ═══════════════════════════════════════════════════════════════════
       6. NOUVEAU PATIENT AJOUTÉ À LA FILE (confirmation)
       → Confirme l'inscription au patient.
    ═══════════════════════════════════════════════════════════════════ */

    public function onNouvelleConsultation(int $consultationId): void
    {
        if (!$this->helper->isConfigured()) return;

        $consult = $this->getConsultationDetails($consultationId);
        if (!$consult) return;

        $token = $this->patientModel->getTokenFCM((int)$consult['patient_id']);
        if (!$token) return;

        $rang        = (int)($consult['rang'] ?? 0);
        $sousService = $consult['sous_service_nom'] ?? 'consultation';
        $heure       = $consult['heure_passage_estimee']
                        ? date('H:i', strtotime($consult['heure_passage_estimee']))
                        : 'à estimer';

        $corps = "Vous êtes enregistré(e) en position {$rang} dans la file de {$sousService}. "
               . "Heure estimée : {$heure}. Vous serez averti(e) au fil de l'avancement.";

        $result = $this->helper->sendToDevice(
            $token,
            '✅ Inscription confirmée',
            $corps,
            [
                'type'            => 'CONFIRMATION',
                'rang'            => (string)$rang,
                'consultation_id' => (string)$consultationId,
                'url'             => '/patient/dashboard.php',
            ]
        );

        $this->persisterNotification(
            (int)$consult['patient_id'],
            $consultationId,
            'CONFIRMATION',
            $corps,
            $result['success']
        );
    }

    /* ═══════════════════════════════════════════════════════════════════
       7. RETARD / DÉCALAGE (le médecin est en retard, horaires mis à jour)
       → Notifie tous les patients de la file que les horaires ont glissé.
    ═══════════════════════════════════════════════════════════════════ */

    public function onDecalageHoraire(int $ssId, int $minutesDecalage): void
    {
        if (!$this->helper->isConfigured()) return;

        $file = $this->getPatientsEnAttente($ssId);

        foreach ($file as $patient) {
            $token = $this->patientModel->getTokenFCM((int)$patient['patient_id']);
            if (!$token) continue;

            $rang    = (int)$patient['rang'];
            $nouvelleHeure = $patient['heure_passage_estimee']
                ? date('H:i', strtotime($patient['heure_passage_estimee']))
                : '—';

            $corps = "Les horaires ont été décalés de {$minutesDecalage} min. "
                   . "Votre nouvelle heure estimée (rang {$rang}) : {$nouvelleHeure}.";

            $result = $this->helper->sendToDevice(
                $token,
                '⚠️ Horaire modifié',
                $corps,
                [
                    'type'            => 'DECALAGE',
                    'rang'            => (string)$rang,
                    'consultation_id' => (string)$patient['id'],
                    'url'             => '/patient/dashboard.php',
                ]
            );

            $this->persisterNotification(
                (int)$patient['patient_id'],
                (int)$patient['id'],
                'DECALAGE',
                $corps,
                $result['success']
            );
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
       8. MISE EN PAUSE (patient parti en examen)
       → Informe le patient mis en pause et avance les autres.
    ═══════════════════════════════════════════════════════════════════ */

    public function onMiseEnPause(int $consultationId, int $ssId, string $motif = ''): void
    {
        if (!$this->helper->isConfigured()) return;

        $consult = $this->getConsultationDetails($consultationId);
        if (!$consult) return;

        $token = $this->patientModel->getTokenFCM((int)$consult['patient_id']);
        if ($token) {
            $sousService = $consult['sous_service_nom'] ?? 'consultation';
            $corps = "Votre consultation en {$sousService} est suspendue le temps de votre examen"
                   . ($motif ? " ({$motif})" : '')
                   . ". Revenez dès que possible, vous serez rappelé(e) en priorité.";

            $result = $this->helper->sendToDevice(
                $token,
                '⏸️ Consultation suspendue',
                $corps,
                [
                    'type'            => 'MISE_EN_PAUSE',
                    'consultation_id' => (string)$consultationId,
                    'url'             => '/patient/dashboard.php',
                ]
            );

            $this->persisterNotification(
                (int)$consult['patient_id'],
                $consultationId,
                'MISE_EN_PAUSE',
                $corps,
                $result['success']
            );
        }

        // Notifier l'avancement des patients restants
        $this->onConsultationTerminee($consultationId, $ssId);
    }

    /* ═══════════════════════════════════════════════════════════════════
       9. RETOUR D'EXAMEN — Gestionnaire sonne la cloche
       → Notifie le patient qu'il peut se présenter à nouveau.
    ═══════════════════════════════════════════════════════════════════ */

    public function onRetourExamen(int $consultationId): void
    {
        if (!$this->helper->isConfigured()) return;

        $consult = $this->getConsultationDetails($consultationId);
        if (!$consult) return;

        $token = $this->patientModel->getTokenFCM((int)$consult['patient_id']);
        if (!$token) return;

        $sousService = $consult['sous_service_nom'] ?? 'consultation';
        $corps = "Le Dr est prêt à vous recevoir à nouveau en {$sousService}. "
               . "Présentez-vous dès maintenant.";

        $result = $this->helper->sendToDevice(
            $token,
            '▶️ Reprenez votre consultation',
            $corps,
            [
                'type'            => 'RETOUR_EXAMEN',
                'consultation_id' => (string)$consultationId,
                'url'             => '/patient/dashboard.php',
            ]
        );

        $this->persisterNotification(
            (int)$consult['patient_id'],
            $consultationId,
            'RETOUR_EXAMEN',
            $corps,
            $result['success']
        );
    }

    /* ═══════════════════════════════════════════════════════════════════
       10. URGENCE (toutes consultations annulées / reportées)
       → Broadcast à tous les patients de la file.
    ═══════════════════════════════════════════════════════════════════ */

    public function onUrgenceOuReport(int $ssId, string $message = ''): void
    {
        if (!$this->helper->isConfigured()) return;

        $file = $this->getPatientsEnAttente($ssId);

        $corps = $message ?: "Les consultations de ce sous-service sont temporairement interrompues. "
                            . "Vous serez contacté(e) dès la reprise.";

        foreach ($file as $patient) {
            $token = $this->patientModel->getTokenFCM((int)$patient['patient_id']);
            if (!$token) continue;

            $result = $this->helper->sendToDevice(
                $token,
                '🚨 Information importante',
                $corps,
                [
                    'type'            => 'URGENCE',
                    'consultation_id' => (string)$patient['id'],
                    'url'             => '/patient/dashboard.php',
                ]
            );

            $this->persisterNotification(
                (int)$patient['patient_id'],
                (int)$patient['id'],
                'URGENCE',
                $corps,
                $result['success']
            );
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
       CRON : Vérification automatique des rappels 30 min
       À appeler toutes les 5 minutes par un cron job.
       Vérifie tous les sous-services et envoie les rappels nécessaires.
    ═══════════════════════════════════════════════════════════════════ */

    public function processRappels30Min(): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.patient_id, c.sous_service_id,
                    c.rang, c.heure_passage_estimee,
                    TIMESTAMPDIFF(MINUTE, NOW(), c.heure_passage_estimee) AS minutes_restantes
             FROM consultations c
             WHERE c.statut IN ('en_attente', 'confirme')
               AND c.heure_passage_estimee IS NOT NULL
               AND TIMESTAMPDIFF(MINUTE, NOW(), c.heure_passage_estimee) BETWEEN 25 AND 35
               AND DATE(c.heure_passage_estimee) = CURDATE()"
        );
        $stmt->execute();
        $aRappeler = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sent = 0;
        $skip = 0;

        foreach ($aRappeler as $row) {
            if ($this->dejaNotifieType((int)$row['id'], 'RAPPEL_30MIN', 60)) {
                $skip++;
                continue;
            }
            $this->onRappel30Min((int)$row['id']);
            $sent++;
        }

        return ['rappels_envoyes' => $sent, 'rappels_ignores' => $skip];
    }

    /* ═══════════════════════════════════════════════════════════════════
       MÉTHODES PRIVÉES
    ═══════════════════════════════════════════════════════════════════ */

    /**
     * Récupère les patients EN ATTENTE d'un sous-service (ordonnés par rang).
     */
    private function getPatientsEnAttente(int $ssId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.rang, c.patient_id, c.heure_passage_estimee,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.token_fcm,
                    ss.nom AS sous_service_nom
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             JOIN sous_services ss ON ss.id = c.sous_service_id
             WHERE c.sous_service_id = :ss
               AND c.statut IN ('en_attente', 'confirme')
               AND DATE(COALESCE(c.heure_passage_estimee, c.heure_emission)) = CURDATE()
             ORDER BY c.rang ASC"
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les détails complets d'une consultation.
     */
    private function getConsultationDetails(int $consultationId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.rang, c.patient_id, c.sous_service_id, c.medecin_id,
                    c.statut, c.heure_passage_estimee,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.token_fcm,
                    m.nom AS medecin_nom, m.prenom AS medecin_prenom,
                    ss.nom AS sous_service_nom
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             LEFT JOIN medecins m ON m.id = c.medecin_id
             JOIN sous_services ss ON ss.id = c.sous_service_id
             WHERE c.id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $consultationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Vérifie si une notification d'un certain type a déjà été envoyée
     * pour cette consultation dans les N dernières minutes.
     */
    private function dejaNotifieType(int $consultationId, string $type, int $minutes = 60): bool
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM notifications
             WHERE consultation_id = :cid
               AND type = :type
               AND statut IN ('envoye', 'en_attente')
               AND created_at >= DATE_SUB(NOW(), INTERVAL :min MINUTE)"
        );
        $stmt->execute([
            ':cid'  => $consultationId,
            ':type' => $type,
            ':min'  => $minutes,
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Persiste une notification en base de données.
     */
    private function persisterNotification(
        int     $patientId,
        int     $consultationId,
        string  $type,
        string  $contenu,
        bool    $success
    ): void {
        try {
            $this->notifModel->creer([
                'patient_id'      => $patientId,
                'consultation_id' => $consultationId,
                'type'            => $type,
                'contenu'         => $contenu,
                'canal'           => 'FCM',
                'statut'          => $success ? 'envoye' : 'echec',
                'sent_at'         => $success ? date('Y-m-d H:i:s') : null,
            ]);
        } catch (\Throwable $e) {
            error_log("[QueueNotificationService] Erreur persistance: " . $e->getMessage());
        }
    }
}