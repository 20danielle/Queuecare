<?php
/**
 * models/ServiceModel.php
 * Modèle MVC — Gestion des sous-services, horaires du service unique et médecins
 * 
 * VERSION MONO-SERVICE
 * - Suppression de toutes les méthodes liées à la gestion des services (CRUD)
 * - Conservation des sous-services, horaires (service_id = 1), et jours de travail
 * - Ajout des méthodes nécessaires pour le mono-service
 */

require_once __DIR__ . '/../config/database.php';

class ServiceModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /* ═══════════════════════════════════════════════════════════
       SERVICE UNIQUE (id = 1)
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère le service par son ID (utile pour le service unique)
     */
    public function getServiceById(int $id)
    {
        $stmt = $this->db->prepare('SELECT * FROM services WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Crée le service par défaut (id=1) s'il n'existe pas
     */
    public function creerServiceParDefaut(): bool
    {
        // Vérifier si le service existe déjà
        $existing = $this->getServiceById(1);
        if ($existing) {
            return true;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO services (id, nom, description, adresse, horaires_ouverture, horaires_fermeture, statut)
             VALUES (1, "CMA Tyo de Baleng", "Centre Médical d\'Arrondissement", "PMI, entrée école normale", "08:00:00", "18:00:00", "actif")'
        );
        return $stmt->execute();
    }

    /**
     * Met à jour les horaires du service unique
     */
    public function updateHoraires(int $serviceId, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE services 
             SET horaires_ouverture = :ouverture, 
                 horaires_fermeture = :fermeture,
                 pause_debut = :pause_debut, 
                 pause_fin = :pause_fin,
                 jours_fermeture = :jours_fermeture
             WHERE id = :id'
        );
        return $stmt->execute([
            ':id'               => $serviceId,
            ':ouverture'        => $data['horaires_ouverture'] ?? '08:00:00',
            ':fermeture'        => $data['horaires_fermeture'] ?? '18:00:00',
            ':pause_debut'      => $data['pause_debut'] ?? null,
            ':pause_fin'        => $data['pause_fin'] ?? null,
            ':jours_fermeture'  => $data['jours_fermeture'] ?? ''
        ]);
    }

    /* ═══════════════════════════════════════════════════════════
       SOUS-SERVICES (CRUD complet - mono-service)
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère tous les sous-services (service_id = 1 par défaut)
     */
    public function getAllSousServices(): array
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*, s.nom as service_nom, s.id as service_id
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE s.id = 1
             ORDER BY ss.nom'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Récupère les sous-services d'un service spécifique (pour compatibilité)
     */
    public function getSousServicesParService(int $serviceId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ss.*,
                    (SELECT COUNT(*) FROM medecin_sous_service mss WHERE mss.sous_service_id = ss.id) AS nb_medecins,
                    (SELECT COUNT(*) FROM gestionnaires g WHERE g.sous_service_id = ss.id) AS nb_gestionnaires
             FROM sous_services ss
             WHERE ss.service_id = :sid
             ORDER BY ss.nom'
        );
        $stmt->execute([':sid' => $serviceId]);
        return $stmt->fetchAll();
    }

    /**
     * Récupère un sous-service par son nom et service_id
     */
    public function getSousServiceParNom(string $nom, int $serviceId)
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM sous_services WHERE nom = :nom AND service_id = :sid LIMIT 1'
        );
        $stmt->execute([':nom' => $nom, ':sid' => $serviceId]);
        return $stmt->fetch();
    }

    /**
     * Récupère un sous-service par son ID
     */
    public function getSousServiceById(int $id)
    {
        $stmt = $this->db->prepare('SELECT * FROM sous_services WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Vérifie si un nom de sous-service existe déjà
     */
    public function nomSsExiste(string $nom, int $serviceId, ?int $exclureId = null): bool
    {
        if ($exclureId) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM sous_services 
                 WHERE nom = :nom AND service_id = :sid AND id != :id'
            );
            $stmt->execute([':nom' => $nom, ':sid' => $serviceId, ':id' => $exclureId]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM sous_services WHERE nom = :nom AND service_id = :sid'
            );
            $stmt->execute([':nom' => $nom, ':sid' => $serviceId]);
        }
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Crée un nouveau sous-service
     */
    public function creerSousService(array $d): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO sous_services (service_id, nom, description, duree_rdv_defaut, duree_estimee, capacite_horaire, statut)
             VALUES (:sid, :nom, :desc, :duree_rdv, :duree_est, :capa, "actif")'
        );
        return $stmt->execute([
            ':sid'       => (int)$d['service_id'],
            ':nom'       => htmlspecialchars(trim($d['nom'])),
            ':desc'      => htmlspecialchars(trim($d['description'] ?? '')),
            ':duree_rdv' => (int)($d['duree_rdv_defaut'] ?? 1800),
            ':duree_est' => (int)($d['duree_rdv_defaut'] ?? 1800),
            ':capa'      => (int)($d['capacite_horaire'] ?? 10),
        ]);
    }

    /**
     * Modifie un sous-service existant
     */
    public function modifierSousService(int $id, array $d): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE sous_services 
             SET nom = :nom, 
                 description = :description, 
                 duree_rdv_defaut = :duree_rdv,
                 duree_estimee = :duree_est,
                 capacite_horaire = :capacite, 
                 statut = :statut
             WHERE id = :id'
        );
        return $stmt->execute([
            ':id'          => $id,
            ':nom'         => htmlspecialchars(trim($d['nom'])),
            ':description' => htmlspecialchars(trim($d['description'] ?? '')),
            ':duree_rdv'   => (int)($d['duree_rdv_defaut'] ?? 1800),
            ':duree_est'   => (int)($d['duree_rdv_defaut'] ?? 1800),
            ':capacite'    => (int)($d['capacite_horaire'] ?? 10),
            ':statut'      => $d['statut'] ?? 'actif'
        ]);
    }

    /**
     * Bascule le statut actif/inactif d'un sous-service
     */
    public function basculerStatutSs(int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE sous_services 
             SET statut = IF(statut = "actif", "inactif", "actif") 
             WHERE id = :id'
        );
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Récupère le statut actuel d'un sous-service
     */
    public function getStatutSs(int $id): ?string
    {
        $stmt = $this->db->prepare('SELECT statut FROM sous_services WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ? $result['statut'] : null;
    }

    /**
     * Supprime un sous-service (vérifie les dépendances)
     */
    public function supprimerSousService(int $id): array
    {
        // Vérifier les consultations associées
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM consultations WHERE sous_service_id = :id');
        $stmt->execute([':id' => $id]);
        $nbConsult = (int)$stmt->fetchColumn();

        if ($nbConsult > 0) {
            return ['ok' => false, 'msg' => "Impossible de supprimer : {$nbConsult} consultation(s) associée(s)."];
        }

        // Vérifier les médecins associés
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM medecin_sous_service WHERE sous_service_id = :id');
        $stmt->execute([':id' => $id]);
        $nbMedecins = (int)$stmt->fetchColumn();

        if ($nbMedecins > 0) {
            return ['ok' => false, 'msg' => "Impossible de supprimer : {$nbMedecins} médecin(s) associé(s)."];
        }

        // Supprimer
        $stmt = $this->db->prepare('DELETE FROM sous_services WHERE id = :id');
        $ok = $stmt->execute([':id' => $id]);
        return $ok ? ['ok' => true, 'msg' => 'Sous-service supprimé avec succès.'] : ['ok' => false, 'msg' => 'Erreur lors de la suppression.'];
    }

    /* ═══════════════════════════════════════════════════════════
       MÉDECINS (pour la gestion des jours de travail)
    ═══════════════════════════════════════════════════════════ */

    /**
     * Récupère tous les médecins (quel que soit leur sous-service)
     */
    public function getAllMedecins(): array
    {
        $stmt = $this->db->query(
            'SELECT m.*, 
                    GROUP_CONCAT(ss.nom SEPARATOR ", ") as ss_noms
             FROM medecins m
             LEFT JOIN medecin_sous_service mss ON mss.medecin_id = m.id
             LEFT JOIN sous_services ss ON ss.id = mss.sous_service_id
             GROUP BY m.id
             ORDER BY m.nom, m.prenom'
        );
        return $stmt->fetchAll();
    }

    /**
     * Récupère les médecins d'un service spécifique (pour compatibilité)
     * En mono-service, on retourne tous les médecins car service_id a été supprimé
     */
    public function getMedecinsParService(int $serviceId): array
    {
        // En mono-service, on ignore serviceId et on retourne tous les médecins
        return $this->getAllMedecins();
    }

    /**
     * Récupère un médecin par son ID
     */
    public function getMedecinParId(int $id)
    {
        $stmt = $this->db->prepare('SELECT * FROM medecins WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Récupère les jours de travail d'un médecin
     */
    public function getJoursTravailMedecin(int $medecinId): array
    {
        $stmt = $this->db->prepare(
            'SELECT jour_semaine FROM medecin_jours_travail 
             WHERE medecin_id = :mid AND actif = 1'
        );
        $stmt->execute([':mid' => $medecinId]);
        $result = $stmt->fetchAll();
        return array_column($result, 'jour_semaine');
    }

    /**
     * Sauvegarde les jours de travail d'un médecin
     */
    public function sauvegarderJoursTravailMedecin(int $medecinId, array $jours): bool
    {
        try {
            // Supprimer les anciens
            $stmt = $this->db->prepare('DELETE FROM medecin_jours_travail WHERE medecin_id = :mid');
            $stmt->execute([':mid' => $medecinId]);

            // Insérer les nouveaux
            $stmt = $this->db->prepare(
                'INSERT INTO medecin_jours_travail (medecin_id, jour_semaine, actif)
                 VALUES (:mid, :jour, 1)'
            );

            foreach ($jours as $jour) {
                $stmt->execute([':mid' => $medecinId, ':jour' => (int)$jour]);
            }
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /* ═══════════════════════════════════════════════════════════
       JOURS DE LA SEMAINE (helper)
    ═══════════════════════════════════════════════════════════ */

    /**
     * Retourne la liste des jours de semaine avec leurs noms
     */
    public function getJoursSemaine(): array
    {
        return [
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            7 => 'Dimanche'
        ];
    }
}