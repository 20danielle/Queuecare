<?php
/**
 * helpers/TicketHelper.php
 * Helper pour la création et gestion des tickets virtuels
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/DateHelper.php';

class TicketHelper
{
    /**
     * Crée un ticket virtuel après scan d'un QR code
     * 
     * @param int $qrCodeId ID du QR code scanné
     * @param int $patientId ID du patient
     * @return array|null Retourne ['ticket_id' => id, 'rang' => rang] ou null en cas d'erreur
     */
    public static function creerTicketDepuisQR(int $qrCodeId, int $patientId): ?array
    {
        $db = Database::getInstance()->getConnection();
        
        // Récupérer les informations du QR code et du sous-service
        $stmt = $db->prepare(
            'SELECT qc.*, ss.duree_estimee, ss.nom as sous_service_nom
             FROM qr_codes qc
             JOIN sous_services ss ON ss.id = qc.sous_service_id
             WHERE qc.id = :id'
        );
        $stmt->execute([':id' => $qrCodeId]);
        $qrCode = $stmt->fetch();
        
        if (!$qrCode) {
            return null;
        }
        
        // Calculer le rang
        $rang = self::calculerRang($qrCodeId, $db);
        
        // Récupérer la durée moyenne en minutes
        $dureeMoyenneMinutes = ceil(($qrCode['duree_estimee'] ?? 1800) / 60);
        
        // Calculer le temps d'attente
        $tempsAttenteMinutes = ($rang - 1) * $dureeMoyenneMinutes;
        
        // Calculer les heures estimées
        $now = new DateTime();
        $debutEstimee = clone $now;
        $debutEstimee->modify("+{$tempsAttenteMinutes} minutes");
        $finEstimee = clone $debutEstimee;
        $finEstimee->modify("+{$dureeMoyenneMinutes} minutes");
        
        // Créer le ticket
        $stmt = $db->prepare(
            'INSERT INTO tickets (patient_id, qr_code_id, rang, heure_creation, 
                                  heure_debut_estimee, heure_fin_estimee, temps_attente_minutes, statut)
             VALUES (:patient_id, :qr_code_id, :rang, NOW(), :debut, :fin, :temps, "en_attente")'
        );
        
        $success = $stmt->execute([
            ':patient_id' => $patientId,
            ':qr_code_id' => $qrCodeId,
            ':rang' => $rang,
            ':debut' => $debutEstimee->format('Y-m-d H:i:s'),
            ':fin' => $finEstimee->format('Y-m-d H:i:s'),
            ':temps' => $tempsAttenteMinutes
        ]);
        
        if (!$success) {
            return null;
        }
        
        $ticketId = (int)$db->lastInsertId();
        
        return [
            'ticket_id' => $ticketId,
            'rang' => $rang,
            'temps_attente' => $tempsAttenteMinutes,
            'debut_estime' => $debutEstimee->format('H:i'),
            'fin_estime' => $finEstimee->format('H:i')
        ];
    }
    
    /**
     * Calcule le rang pour un nouveau ticket
     */
    private static function calculerRang(int $qrCodeId, PDO $db): int
    {
        $stmt = $db->prepare(
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
     * Récupère la position actuelle d'un ticket dans la file
     */
    public static function getPositionActuelle(int $ticketId): ?int
    {
        $db = Database::getInstance()->getConnection();
        
        $stmt = $db->prepare(
            'SELECT t.rang, 
                    (SELECT COUNT(*) + 1 
                     FROM tickets t2 
                     WHERE t2.qr_code_id = t.qr_code_id 
                       AND t2.statut IN ("en_attente", "en_cours") 
                       AND t2.rang < t.rang) as position_actuelle
             FROM tickets t
             WHERE t.id = :id'
        );
        $stmt->execute([':id' => $ticketId]);
        return $stmt->fetch();
    }
    
    /**
     * Met à jour le temps d'attente pour tous les tickets d'un QR code
     */
    public static function recalculerTempsAttente(int $qrCodeId): void
    {
        $db = Database::getInstance()->getConnection();
        
        // Récupérer la durée estimée du sous-service
        $stmt = $db->prepare(
            'SELECT ss.duree_estimee
             FROM qr_codes qc
             JOIN sous_services ss ON ss.id = qc.sous_service_id
             WHERE qc.id = :id'
        );
        $stmt->execute([':id' => $qrCodeId]);
        $result = $stmt->fetch();
        $dureeMinutes = ceil(($result['duree_estimee'] ?? 1800) / 60);
        
        // Recalculer les temps d'attente pour tous les tickets en attente
        $stmt = $db->prepare(
            'UPDATE tickets 
             SET temps_attente_minutes = (rang - 1) * :duree,
                 heure_debut_estimee = DATE_ADD(NOW(), INTERVAL ((rang - 1) * :duree) MINUTE),
                 heure_fin_estimee = DATE_ADD(NOW(), INTERVAL (rang * :duree) MINUTE)
             WHERE qr_code_id = :qid 
               AND statut = "en_attente"'
        );
        $stmt->execute([':duree' => $dureeMinutes, ':qid' => $qrCodeId]);
    }
}