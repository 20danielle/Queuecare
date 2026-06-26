<?php
/**
 * models/QRCodeModel.php
 * Modèle pour la gestion des QR codes
 */

require_once __DIR__ . '/../config/database.php';

class QRCodeModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Sauvegarde un QR code en base de données
     */
    public function saveQRCode(array $data): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO qr_codes (sous_service_id, token, qr_code_path, expire_at, content, created_by, statut)
             VALUES (:ss_id, :token, :path, :expire_at, :content, :created_by, "actif")'
        );
        
        $success = $stmt->execute([
            ':ss_id'     => $data['sous_service_id'],
            ':token'     => $data['token'],
            ':path'      => $data['qr_code_path'],
            ':expire_at' => $data['expire_at'],
            ':content'   => $data['content'],
            ':created_by'=> $data['created_by']
        ]);
        
        return $success ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Trouve un QR code par son token
     */
    public function findByToken(string $token)
    {
        $stmt = $this->db->prepare(
            'SELECT qc.*, ss.nom as sous_service_nom, ss.duree_estimee
             FROM qr_codes qc
             JOIN sous_services ss ON ss.id = qc.sous_service_id
             WHERE qc.token = :token
             LIMIT 1'
        );
        $stmt->execute([':token' => $token]);
        return $stmt->fetch();
    }

    /**
     * Trouve un QR code par son ID
     */
    public function findById(int $id)
    {
        $stmt = $this->db->prepare('SELECT * FROM qr_codes WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Récupère le QR code actif d'un sous-service
     */
    public function getActiveQRCode(int $sousServiceId)
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM qr_codes 
             WHERE sous_service_id = :ss_id 
               AND statut = "actif" 
               AND expire_at > NOW()
             ORDER BY created_at DESC
             LIMIT 1'
        );
        $stmt->execute([':ss_id' => $sousServiceId]);
        return $stmt->fetch();
    }

    /**
     * Récupère le sous-service d'un gestionnaire
     */
    public function getSousServiceByGestionnaire(int $gestionnaireId)
    {
        $stmt = $this->db->prepare(
            'SELECT ss.* 
             FROM sous_services ss
             JOIN gestionnaires g ON g.sous_service_id = ss.id
             WHERE g.id = :gid
             LIMIT 1'
        );
        $stmt->execute([':gid' => $gestionnaireId]);
        return $stmt->fetch();
    }

    /**
     * Incrémente le compteur de scans d'un QR code
     */
    public function incrementScanCount(int $qrCodeId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE qr_codes SET scan_count = scan_count + 1 WHERE id = :id'
        );
        return $stmt->execute([':id' => $qrCodeId]);
    }

    /**
     * Désactive les QR codes expirés
     */
    public function desactiverExpires(): int
    {
        $stmt = $this->db->prepare(
            'UPDATE qr_codes 
             SET statut = "expire" 
             WHERE expire_at <= NOW() 
               AND statut = "actif"'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Récupère tous les QR codes d'un sous-service
     */
    public function getBySousService(int $sousServiceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM qr_codes 
             WHERE sous_service_id = :ss_id 
             ORDER BY created_at DESC'
        );
        $stmt->execute([':ss_id' => $sousServiceId]);
        return $stmt->fetchAll();
    }
}