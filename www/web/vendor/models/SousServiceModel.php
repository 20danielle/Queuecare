<?php
/**
 * models/SousServiceModel.php
 * Modèle MVC — Gestion des sous-services (spécialités médicales)
 * 
 * VERSION MONO-SERVICE
 * Ce modèle gère les sous-services qui sont rattachés au service unique (id=1)
 */

require_once __DIR__ . '/../config/database.php';

class SousServiceModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /* ═══════════════════════════════════════════════════════════
       RÉCUPÉRATION DES SOUS-SERVICES
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère tous les sous-services (uniquement du service principal)
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*, s.nom as service_nom
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE s.id = 1
             ORDER BY ss.nom'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Récupère tous les sous-services actifs
     */
    public function getAllActifs(): array
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*, s.nom as service_nom
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE s.id = 1 AND ss.statut = "actif"
             ORDER BY ss.nom'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Récupère un sous-service par son ID
     */
    public function getById(int $id)
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*, s.nom as service_nom, s.adresse as service_adresse,
                    s.horaires_ouverture, s.horaires_fermeture, s.pause_debut, s.pause_fin, s.jours_fermeture
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE ss.id = :id'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Récupère un sous-service par son nom
     */
    public function getByNom(string $nom)
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*, s.nom as service_nom
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE ss.nom = :nom AND s.id = 1
             LIMIT 1'
        );
        $stmt->execute([':nom' => $nom]);
        return $stmt->fetch();
    }

    /**
     * Récupère les sous-services associés à un médecin
     */
    public function getByMedecinId(int $medecinId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*
             FROM sous_services ss
             JOIN medecin_sous_service mss ON mss.sous_service_id = ss.id
             WHERE mss.medecin_id = :mid
             ORDER BY ss.nom'
        );
        $stmt->execute([':mid' => $medecinId]);
        return $stmt->fetchAll();
    }

    /**
     * Récupère le sous-service d'un gestionnaire
     */
    public function getByGestionnaireId(int $gestionnaireId)
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*, s.nom as service_nom
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             JOIN gestionnaires g ON g.sous_service_id = ss.id
             WHERE g.id = :gid
             LIMIT 1'
        );
        $stmt->execute([':gid' => $gestionnaireId]);
        return $stmt->fetch();
    }

    /* ═══════════════════════════════════════════════════════════
       CRUD SOUS-SERVICES
    ═══════════════════════════════════════════════════════════ */

    /**
     * Vérifie si un nom de sous-service existe déjà
     */
    public function nomExiste(string $nom, ?int $exclureId = null): bool
    {
        if ($exclureId) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM sous_services 
                 WHERE nom = :nom AND service_id = 1 AND id != :id'
            );
            $stmt->execute([':nom' => $nom, ':id' => $exclureId]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM sous_services WHERE nom = :nom AND service_id = 1'
            );
            $stmt->execute([':nom' => $nom]);
        }
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Crée un nouveau sous-service
     */
    public function creer(array $data): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sous_services (service_id, nom, description, duree_rdv_defaut, duree_estimee, capacite_horaire, statut)
             VALUES (1, :nom, :description, :duree_rdv_defaut, :duree_estimee, :capacite_horaire, :statut)'
        );
        
        $duree = (int)($data['duree_estimee'] ?? $data['duree_rdv_defaut'] ?? 1800);
        
        $success = $stmt->execute([
            ':nom'               => htmlspecialchars(trim($data['nom'])),
            ':description'       => htmlspecialchars(trim($data['description'] ?? '')),
            ':duree_rdv_defaut'  => $duree,
            ':duree_estimee'     => $duree,
            ':capacite_horaire'  => (int)($data['capacite_horaire'] ?? 10),
            ':statut'            => $data['statut'] ?? 'actif'
        ]);
        
        return $success ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Met à jour un sous-service
     */
    public function mettreAJour(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE sous_services 
             SET nom = :nom, 
                 description = :description, 
                 duree_rdv_defaut = :duree_rdv_defaut,
                 duree_estimee = :duree_estimee,
                 capacite_horaire = :capacite_horaire,
                 statut = :statut
             WHERE id = :id'
        );
        
        $duree = (int)($data['duree_estimee'] ?? $data['duree_rdv_defaut'] ?? 1800);
        
        return $stmt->execute([
            ':id'                => $id,
            ':nom'               => htmlspecialchars(trim($data['nom'])),
            ':description'       => htmlspecialchars(trim($data['description'] ?? '')),
            ':duree_rdv_defaut'  => $duree,
            ':duree_estimee'     => $duree,
            ':capacite_horaire'  => (int)($data['capacite_horaire'] ?? 10),
            ':statut'            => $data['statut'] ?? 'actif'
        ]);
    }

    /**
     * Met à jour la durée estimée d'un sous-service (recalcul automatique)
     */
    public function mettreAJourDureeEstimee(int $id, int $dureeSecondes): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE sous_services SET duree_estimee = :duree WHERE id = :id'
        );
        return $stmt->execute([':duree' => $dureeSecondes, ':id' => $id]);
    }

    /**
     * Bascule le statut actif/inactif d'un sous-service
     */
    public function basculerStatut(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE sous_services 
             SET statut = IF(statut = "actif", "inactif", "actif") 
             WHERE id = :id'
        );
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Active un sous-service
     */
    public function activer(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE sous_services SET statut = "actif" WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Désactive un sous-service
     */
    public function desactiver(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE sous_services SET statut = "inactif" WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Supprime un sous-service (vérifie les dépendances)
     */
    public function supprimer(int $id): array
    {
        // Vérifier les consultations associées
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM consultations WHERE sous_service_id = :id');
        $stmt->execute([':id' => $id]);
        $nbConsultations = (int)$stmt->fetchColumn();

        if ($nbConsultations > 0) {
            return [
                'ok' => false, 
                'msg' => "Impossible de supprimer : {$nbConsultations} consultation(s) associée(s)."
            ];
        }

        // Vérifier les médecins associés
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM medecin_sous_service WHERE sous_service_id = :id');
        $stmt->execute([':id' => $id]);
        $nbMedecins = (int)$stmt->fetchColumn();

        if ($nbMedecins > 0) {
            return [
                'ok' => false, 
                'msg' => "Impossible de supprimer : {$nbMedecins} médecin(s) associé(s)."
            ];
        }

        // Vérifier les gestionnaires associés
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM gestionnaires WHERE sous_service_id = :id');
        $stmt->execute([':id' => $id]);
        $nbGestionnaires = (int)$stmt->fetchColumn();

        if ($nbGestionnaires > 0) {
            return [
                'ok' => false, 
                'msg' => "Impossible de supprimer : {$nbGestionnaires} gestionnaire(s) associé(s)."
            ];
        }

        // Supprimer
        $stmt = $this->db->prepare('DELETE FROM sous_services WHERE id = :id');
        $ok = $stmt->execute([':id' => $id]);
        
        return $ok 
            ? ['ok' => true, 'msg' => 'Sous-service supprimé avec succès.'] 
            : ['ok' => false, 'msg' => 'Erreur lors de la suppression.'];
    }

    /* ═══════════════════════════════════════════════════════════
       STATISTIQUES ET MÉTRIQUES
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère les statistiques d'un sous-service
     */
    public function getStats(int $id): array
    {
        $stats = [];
        
        // Nombre de consultations aujourd'hui
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as total,
                    SUM(statut = "traite") as traitees,
                    SUM(statut IN ("en_attente", "confirme")) as en_attente
             FROM consultations
             WHERE sous_service_id = :id AND DATE(heure_emission) = CURDATE()'
        );
        $stmt->execute([':id' => $id]);
        $stats['aujourdhui'] = $stmt->fetch() ?: ['total' => 0, 'traitees' => 0, 'en_attente' => 0];
        
        // Nombre de médecins affectés
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as nb_medecins FROM medecin_sous_service WHERE sous_service_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $stats['nb_medecins'] = (int)$stmt->fetchColumn();
        
        // Nombre de gestionnaires affectés
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as nb_gestionnaires FROM gestionnaires WHERE sous_service_id = :id'
        );
        $stmt->execute([':id' => $id]);
        $stats['nb_gestionnaires'] = (int)$stmt->fetchColumn();
        
        // Durée moyenne réelle (sur les 30 derniers jours)
        $stmt = $this->db->prepare(
            'SELECT AVG(TIMESTAMPDIFF(SECOND, heure_debut_reelle, heure_fin_reelle)) as duree_moyenne
             FROM consultations
             WHERE sous_service_id = :id
               AND statut = "traite"
               AND heure_debut_reelle IS NOT NULL
               AND heure_fin_reelle IS NOT NULL
               AND heure_emission > DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        $stats['duree_moyenne_reelle'] = $result['duree_moyenne'] ? round($result['duree_moyenne'] / 60) : null;
        
        return $stats;
    }

    /**
     * Récupère la durée estimée actuelle d'un sous-service
     */
    public function getDureeEstimee(int $id): int
    {
        $stmt = $this->db->prepare('SELECT duree_estimee FROM sous_services WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ? (int)$result['duree_estimee'] : 1800;
    }

    /**
     * Calcule le temps d'attente estimé pour un sous-service
     * @param int $sousServiceId ID du sous-service
     * @return int Temps d'attente en minutes
     */
    public function calculerTempsAttente(int $sousServiceId): int
    {
        // Récupérer le nombre de personnes en attente
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) as nb_attente
             FROM consultations
             WHERE sous_service_id = :id
               AND statut IN ("en_attente", "confirme")
               AND DATE(heure_emission) = CURDATE()'
        );
        $stmt->execute([':id' => $sousServiceId]);
        $nbAttente = (int)$stmt->fetchColumn();
        
        // Récupérer la durée estimée
        $dureeEstimee = $this->getDureeEstimee($sousServiceId);
        $dureeMinutes = ceil($dureeEstimee / 60);
        
        // Temps d'attente = (nombre de personnes devant) × durée moyenne
        return $nbAttente * $dureeMinutes;
    }

    /* ═══════════════════════════════════════════════════════════
       MÉDECINS ASSOCIÉS
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère tous les médecins d'un sous-service
     */
    public function getMedecins(int $sousServiceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*
             FROM medecins m
             JOIN medecin_sous_service mss ON mss.medecin_id = m.id
             WHERE mss.sous_service_id = :ss_id
             ORDER BY m.nom, m.prenom'
        );
        $stmt->execute([':ss_id' => $sousServiceId]);
        return $stmt->fetchAll();
    }

    /**
     * Affecte un médecin à un sous-service
     */
    public function affecterMedecin(int $sousServiceId, int $medecinId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO medecin_sous_service (medecin_id, sous_service_id, date_affectation)
             VALUES (:mid, :ssid, CURDATE())
             ON DUPLICATE KEY UPDATE date_affectation = date_affectation'
        );
        return $stmt->execute([':mid' => $medecinId, ':ssid' => $sousServiceId]);
    }

    /**
     * Retire l'affectation d'un médecin à un sous-service
     */
    public function retirerMedecin(int $sousServiceId, int $medecinId): bool
    {
        $stmt = $this->db->prepare(
            'DELETE FROM medecin_sous_service 
             WHERE medecin_id = :mid AND sous_service_id = :ssid'
        );
        return $stmt->execute([':mid' => $medecinId, ':ssid' => $sousServiceId]);
    }

    /**
     * Vérifie si un médecin est affecté à un sous-service
     */
    public function estMedecinAffecte(int $sousServiceId, int $medecinId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM medecin_sous_service 
             WHERE medecin_id = :mid AND sous_service_id = :ssid'
        );
        $stmt->execute([':mid' => $medecinId, ':ssid' => $sousServiceId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Récupère les médecins non affectés à un sous-service
     */
    public function getMedecinsNonAffectes(int $sousServiceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.*
             FROM medecins m
             WHERE m.id NOT IN (
                 SELECT medecin_id FROM medecin_sous_service WHERE sous_service_id = :ssid
             )
             ORDER BY m.nom, m.prenom'
        );
        $stmt->execute([':ssid' => $sousServiceId]);
        return $stmt->fetchAll();
    }

    /* ═══════════════════════════════════════════════════════════
       QR CODES ASSOCIÉS
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère le QR code actif d'un sous-service
     */
    public function getQRCodeActif(int $sousServiceId)
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
     * Génère un nouveau QR code pour un sous-service
     */
    public function genererQRCode(int $sousServiceId, int $createdBy, string $token, string $qrCodePath, string $expireAt): int|false
    {
        $baseUrl = $this->getBaseUrl();
        $content = $baseUrl . '/index.php?action=scanner_qr&token=' . $token;
        
        $stmt = $this->db->prepare(
            'INSERT INTO qr_codes (sous_service_id, token, qr_code_path, expire_at, content, created_by, statut)
             VALUES (:ss_id, :token, :path, :expire_at, :content, :created_by, "actif")'
        );
        
        $success = $stmt->execute([
            ':ss_id'     => $sousServiceId,
            ':token'     => $token,
            ':path'      => $qrCodePath,
            ':expire_at' => $expireAt,
            ':content'   => $content,
            ':created_by'=> $createdBy
        ]);
        
        return $success ? (int)$this->db->lastInsertId() : false;
    }

    private function getBaseUrl(): string
    {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $script = dirname($_SERVER['SCRIPT_NAME']);
        return $protocol . '://' . $host . ($script != '/' ? $script : '');
    }
}