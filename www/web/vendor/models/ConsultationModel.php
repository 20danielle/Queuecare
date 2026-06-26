<?php
/**
 * models/ConsultationModel.php
 * Modèle pour la gestion des consultations
 */

require_once __DIR__ . '/../config/database.php';

class ConsultationModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /* ═══════════════════════════════════════════════════════════
       CRUD CONSULTATION
    ═══════════════════════════════════════════════════════════ */

    /**
     * Trouve une consultation par son ID
     */
    public function findById(int $id)
    {
        $stmt = $this->db->prepare('SELECT * FROM consultations WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Trouve une consultation avec toutes les infos patient et médecin
     */
    public function findWithDetails(int $id)
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, 
                    p.nom as patient_nom, p.prenom as patient_prenom, p.telephone as patient_telephone, p.email as patient_email,
                    m.nom as medecin_nom, m.prenom as medecin_prenom, m.specialite,
                    ss.nom as sous_service_nom,
                    s.nom as service_nom
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             LEFT JOIN medecins m ON m.id = c.medecin_id
             JOIN sous_services ss ON ss.id = c.sous_service_id
             JOIN services s ON s.id = ss.service_id
             WHERE c.id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Crée une nouvelle consultation
     */
    public function creer(array $data): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO consultations (patient_id, sous_service_id, medecin_id, emploi_temps_id, qr_code_id,
                                        statut, rang, mode_prise, heure_emission, heure_passage_estimee, 
                                        motif, duree_estimee)
             VALUES (:patient_id, :sous_service_id, :medecin_id, :emploi_temps_id, :qr_code_id,
                     :statut, :rang, :mode_prise, NOW(), :heure_passage_estimee,
                     :motif, :duree_estimee)'
        );
        
        $success = $stmt->execute([
            ':patient_id'            => $data['patient_id'],
            ':sous_service_id'       => $data['sous_service_id'],
            ':medecin_id'            => $data['medecin_id'] ?? null,
            ':emploi_temps_id'       => $data['emploi_temps_id'] ?? null,
            ':qr_code_id'            => $data['qr_code_id'] ?? null,
            ':statut'                => $data['statut'] ?? 'en_attente',
            ':rang'                  => $data['rang'] ?? null,
            ':mode_prise'            => $data['mode_prise'] ?? 'QR_CODE',
            ':heure_passage_estimee' => $data['heure_passage_estimee'] ?? null,
            ':motif'                 => $data['motif'] ?? null,
            ':duree_estimee'         => $data['duree_estimee'] ?? null
        ]);
        
        return $success ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Met à jour une consultation
     */
    public function mettreAJour(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];
        
        $allowedFields = [
            'medecin_id', 'emploi_temps_id', 'qr_code_id', 'statut', 'rang',
            'heure_passage_estimee', 'heure_debut_reelle', 'heure_fin_reelle',
            'motif', 'duree_estimee', 'mode_prise'
        ];
        
        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "$field = :$field";
                $params[":$field"] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $sql = 'UPDATE consultations SET ' . implode(', ', $fields) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Met à jour le statut d'une consultation
     */
    public function mettreAJourStatut(int $id, string $statut): bool
    {
        $allowed = ['en_attente', 'confirme', 'en_cours', 'traite', 'annule', 'absent'];
        if (!in_array($statut, $allowed)) {
            return false;
        }
        
        $stmt = $this->db->prepare('UPDATE consultations SET statut = :statut WHERE id = :id');
        return $stmt->execute([':statut' => $statut, ':id' => $id]);
    }

    /* ═══════════════════════════════════════════════════════════
       CONSULTATIONS PAR FILTRE
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère les consultations du jour pour un sous-service
     */
    public function getConsultationsDuJour(int $sousServiceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, p.nom as patient_nom, p.prenom as patient_prenom, p.telephone as patient_telephone,
                    CONCAT(m.prenom, " ", m.nom) as medecin_nom
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             LEFT JOIN medecins m ON m.id = c.medecin_id
             WHERE c.sous_service_id = :ss_id
               AND DATE(c.heure_emission) = CURDATE()
             ORDER BY c.rang ASC'
        );
        $stmt->execute([':ss_id' => $sousServiceId]);
        return $stmt->fetchAll();
    }

    /**
     * Récupère les consultations en attente pour un sous-service
     */
    public function getConsultationsEnAttente(int $sousServiceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, p.nom as patient_nom, p.prenom as patient_prenom
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             WHERE c.sous_service_id = :ss_id
               AND c.statut IN ("en_attente", "confirme")
               AND DATE(c.heure_emission) = CURDATE()
             ORDER BY c.rang ASC'
        );
        $stmt->execute([':ss_id' => $sousServiceId]);
        return $stmt->fetchAll();
    }

    /**
     * Récupère la consultation en cours pour un sous-service
     */
    public function getConsultationEnCours(int $sousServiceId)
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, p.nom as patient_nom, p.prenom as patient_prenom,
                    CONCAT(m.prenom, " ", m.nom) as medecin_nom
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             LEFT JOIN medecins m ON m.id = c.medecin_id
             WHERE c.sous_service_id = :ss_id
               AND c.statut = "en_cours"
               AND DATE(c.heure_emission) = CURDATE()
             LIMIT 1'
        );
        $stmt->execute([':ss_id' => $sousServiceId]);
        return $stmt->fetch();
    }

    /**
     * Récupère les consultations d'un médecin pour aujourd'hui
     */
    public function getConsultationsMedecinJour(int $medecinId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, p.nom as patient_nom, p.prenom as patient_prenom, p.telephone as patient_telephone
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             WHERE c.medecin_id = :mid
               AND DATE(c.heure_emission) = CURDATE()
             ORDER BY c.rang ASC'
        );
        $stmt->execute([':mid' => $medecinId]);
        return $stmt->fetchAll();
    }

    /* ═══════════════════════════════════════════════════════════
       STATISTIQUES
    ═══════════════════════════════════════════════════════════ */

    /**
     * Statistiques du jour pour un sous-service
     */
    public function getStatsJour(int $sousServiceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT 
                COUNT(*) as total,
                SUM(statut = "traite") as traitees,
                SUM(statut IN ("en_attente", "confirme")) as en_attente,
                SUM(statut = "absent") as absentes,
                SUM(statut = "annule") as annulees,
                SUM(mode_prise = "LIGNE") as prises_en_ligne,
                SUM(mode_prise = "PLACE") as prises_sur_place,
                SUM(mode_prise = "QR_CODE") as prises_qr_code,
                SUM(mode_prise = "MANUEL") as prises_manuel
             FROM consultations
             WHERE sous_service_id = :ss_id
               AND DATE(heure_emission) = CURDATE()'
        );
        $stmt->execute([':ss_id' => $sousServiceId]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Calcule le prochain rang disponible pour un sous-service
     */
    public function getProchainRang(int $sousServiceId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(MAX(rang), 0) + 1 as prochain_rang
             FROM consultations
             WHERE sous_service_id = :ss_id
               AND DATE(heure_emission) = CURDATE()
               AND statut NOT IN ("annule", "absent")'
        );
        $stmt->execute([':ss_id' => $sousServiceId]);
        $result = $stmt->fetch();
        return $result['prochain_rang'] ?? 1;
    }

    /**
     * Calcule le temps d'attente estimé pour une nouvelle consultation
     */
    public function calculerTempsAttente(int $sousServiceId, int $dureeEstimeeSecondes): int
    {
        $rang = $this->getProchainRang($sousServiceId);
        return ($rang - 1) * ceil($dureeEstimeeSecondes / 60);
    }

    /* ═══════════════════════════════════════════════════════════
       ACTIONS SUR LES CONSULTATIONS
    ═══════════════════════════════════════════════════════════ */

    /**
     * Démarre une consultation
     */
    public function demarrer(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations 
             SET statut = "en_cours", heure_debut_reelle = NOW()
             WHERE id = :id AND statut IN ("en_attente", "confirme")'
        );
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Termine une consultation
     */
    public function terminer(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations 
             SET statut = "traite", heure_fin_reelle = NOW()
             WHERE id = :id AND statut = "en_cours"'
        );
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Marque un patient comme absent
     */
    public function marquerAbsent(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations 
             SET statut = "absent"
             WHERE id = :id AND statut IN ("en_attente", "confirme")'
        );
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Annule une consultation
     */
    public function annuler(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations 
             SET statut = "annule"
             WHERE id = :id AND statut NOT IN ("traite")'
        );
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Lie un ticket à une consultation
     */
    public function lierTicket(int $consultationId, int $ticketId): bool
    {
        $stmt = $this->db->prepare('UPDATE tickets SET consultation_id = :cid WHERE id = :tid');
        $stmt->execute([':cid' => $consultationId, ':tid' => $ticketId]);
        
        $stmt2 = $this->db->prepare('UPDATE consultations SET qr_code_id = (SELECT qr_code_id FROM tickets WHERE id = :tid) WHERE id = :cid');
        return $stmt2->execute([':tid' => $ticketId, ':cid' => $consultationId]);
    }
}