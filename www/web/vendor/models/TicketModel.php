<?php
/**
 * models/TicketModel.php
 * Modèle pour la gestion des tickets virtuels
 */

require_once __DIR__ . '/../config/database.php';

class TicketModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Crée un nouveau ticket
     */
    public function creer(array $data): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tickets (patient_id, qr_code_id, consultation_id, rang, heure_creation, 
                                  heure_debut_estimee, heure_fin_estimee, temps_attente_minutes, statut)
             VALUES (:patient_id, :qr_code_id, :consultation_id, :rang, :heure_creation,
                     :heure_debut_estimee, :heure_fin_estimee, :temps_attente_minutes, :statut)'
        );
        
        $success = $stmt->execute([
            ':patient_id'            => $data['patient_id'],
            ':qr_code_id'            => $data['qr_code_id'],
            ':consultation_id'       => $data['consultation_id'] ?? null,
            ':rang'                  => $data['rang'],
            ':heure_creation'        => $data['heure_creation'] ?? date('Y-m-d H:i:s'),
            ':heure_debut_estimee'   => $data['heure_debut_estimee'] ?? null,
            ':heure_fin_estimee'     => $data['heure_fin_estimee'] ?? null,
            ':temps_attente_minutes' => $data['temps_attente_minutes'] ?? null,
            ':statut'                => $data['statut'] ?? 'en_attente'
        ]);
        
        return $success ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Récupère un ticket par son ID
     */
    public function obtenirParId(int $id)
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, 
                    p.nom as patient_nom, p.prenom as patient_prenom, p.telephone as patient_telephone,
                    qc.token as qr_token, qc.content as qr_content,
                    ss.nom as sous_service_nom
             FROM tickets t
             JOIN patients p ON p.id = t.patient_id
             JOIN qr_codes qc ON qc.id = t.qr_code_id
             JOIN sous_services ss ON ss.id = qc.sous_service_id
             WHERE t.id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Récupère le dernier ticket d'un QR code
     */
    public function obtenirParQRCode(int $qrCodeId)
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM tickets 
             WHERE qr_code_id = :qid 
             ORDER BY id DESC 
             LIMIT 1'
        );
        $stmt->execute([':qid' => $qrCodeId]);
        return $stmt->fetch();
    }

    /**
     * Récupère tous les tickets d'un patient
     */
    public function obtenirParPatient(int $patientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, ss.nom as sous_service_nom
             FROM tickets t
             JOIN qr_codes qc ON qc.id = t.qr_code_id
             JOIN sous_services ss ON ss.id = qc.sous_service_id
             WHERE t.patient_id = :pid
             ORDER BY t.created_at DESC'
        );
        $stmt->execute([':pid' => $patientId]);
        return $stmt->fetchAll();
    }

    /**
     * Calcule le rang pour un nouveau ticket
     */
    public function calculerRang(int $qrCodeId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) + 1 as rang 
             FROM tickets 
             WHERE qr_code_id = :qid 
               AND statut IN ("en_attente", "en_cours")'
        );
        $stmt->execute([':qid' => $qrCodeId]);
        $result = $stmt->fetch();
        return $result['rang'] ?? 1;
    }

    /**
     * Calcule le temps d'attente estimé
     */
    public function calculerTempsAttente(int $qrCodeId, int $dureeMoyenneMinutes): int
    {
        $rang = $this->calculerRang($qrCodeId);
        return ($rang - 1) * $dureeMoyenneMinutes;
    }

    /**
     * Met à jour le statut d'un ticket
     */
    public function mettreAJourStatut(int $id, string $statut): bool
    {
        $allowed = ['en_attente', 'en_cours', 'termine', 'absent', 'annule'];
        if (!in_array($statut, $allowed)) {
            return false;
        }
        
        $stmt = $this->db->prepare('UPDATE tickets SET statut = :statut WHERE id = :id');
        return $stmt->execute([':statut' => $statut, ':id' => $id]);
    }

    /**
     * Met à jour les heures estimées d'un ticket
     */
    public function mettreAJourHeuresEstimees(int $id, string $debutEstimee, string $finEstimee, int $tempsAttente): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE tickets 
             SET heure_debut_estimee = :debut, 
                 heure_fin_estimee = :fin, 
                 temps_attente_minutes = :temps
             WHERE id = :id'
        );
        return $stmt->execute([
            ':debut' => $debutEstimee,
            ':fin' => $finEstimee,
            ':temps' => $tempsAttente,
            ':id' => $id
        ]);
    }

    /**
     * Lie un ticket à une consultation
     */
    public function lierConsultation(int $ticketId, int $consultationId): bool
    {
        $stmt = $this->db->prepare('UPDATE tickets SET consultation_id = :cid WHERE id = :tid');
        return $stmt->execute([':cid' => $consultationId, ':tid' => $ticketId]);
    }

    /**
     * Récupère le ticket en attente pour un QR code
     */
    public function getTicketEnAttente(int $qrCodeId)
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM tickets 
             WHERE qr_code_id = :qid 
               AND statut = "en_attente"
             ORDER BY rang ASC
             LIMIT 1'
        );
        $stmt->execute([':qid' => $qrCodeId]);
        return $stmt->fetch();
    }

    /**
     * Passe au ticket suivant (incrémente le rang effectif)
     */
    public function passerSuivant(int $qrCodeId): bool
    {
        // Marquer le ticket actuel comme terminé
        $stmt = $this->db->prepare(
            'UPDATE tickets 
             SET statut = "termine" 
             WHERE qr_code_id = :qid 
               AND statut = "en_cours"'
        );
        $stmt->execute([':qid' => $qrCodeId]);
        
        // Passer le ticket suivant en "en_cours"
        $stmt = $this->db->prepare(
            'UPDATE tickets 
             SET statut = "en_cours" 
             WHERE qr_code_id = :qid 
               AND statut = "en_attente"
             ORDER BY rang ASC
             LIMIT 1'
        );
        return $stmt->execute([':qid' => $qrCodeId]);
    }

    /**
     * Statistiques des tickets pour un QR code
     */
    public function getStats(int $qrCodeId): array
    {
        $stmt = $this->db->prepare(
            'SELECT 
                COUNT(*) as total,
                SUM(statut = "en_attente") as en_attente,
                SUM(statut = "en_cours") as en_cours,
                SUM(statut = "termine") as termines,
                SUM(statut = "absent") as absents,
                SUM(statut = "annule") as annules
             FROM tickets
             WHERE qr_code_id = :qid'
        );
        $stmt->execute([':qid' => $qrCodeId]);
        return $stmt->fetch() ?: [];
    }
}