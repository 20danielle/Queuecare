<?php
/**
 * HopitalModel.php — CRUD sur la table configuration_hopital
 *
 * Schéma réel :
 *   id, nom_hopital, adresse, telephone, email, logo_path,
 *   setup_completed (tinyint), date_installation, date_mise_a_jour
 *
 * Les horaires généraux des médecins (ouverture, fermeture, pause) sont
 * stockés dans la table `services` (id = 1) et lus depuis là par les
 * dashboards médecin et gestionnaire via ServiceModel/MedecinModel.
 */

require_once __DIR__ . '/../config/database.php';

class HopitalModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Retourne true si la configuration a été complétée (setup_completed = 1)
     * ou si une ligne existe déjà.
     */
    public function estConfigure(): bool {
        $stmt = $this->db->query(
            "SELECT COUNT(*) FROM configuration_hopital WHERE setup_completed = 1"
        );
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Récupère la configuration de l'hôpital (première ligne)
     *
     * @return array|false
     */
    public function getData() {
        $stmt = $this->db->query("SELECT * FROM configuration_hopital LIMIT 1");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les horaires généraux des médecins depuis la table services (id=1).
     * C'est la source de vérité partagée avec les dashboards médecin et gestionnaire.
     *
     * @return array
     */
    public function getHorairesGeneraux(): array {
        $stmt = $this->db->query(
            "SELECT horaires_ouverture, horaires_fermeture, pause_debut, pause_fin
             FROM services WHERE id = 1 LIMIT 1"
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [
            'horaires_ouverture' => '08:00:00',
            'horaires_fermeture' => '18:00:00',
            'pause_debut'        => null,
            'pause_fin'          => null,
        ];
    }

    /**
     * Met à jour les horaires généraux des médecins dans la table services (id=1).
     * Les dashboards médecin et gestionnaire liront automatiquement ces nouvelles valeurs.
     *
     * @param string      $heureDebut   Format HH:MM
     * @param string      $heureFin     Format HH:MM
     * @param string|null $pauseDebut   Format HH:MM ou null
     * @param string|null $pauseFin     Format HH:MM ou null
     * @return bool
     */
    public function sauvegarderHorairesGeneraux(
        string $heureDebut,
        string $heureFin,
        ?string $pauseDebut,
        ?string $pauseFin
    ): bool {
        // Vérifier que le service existe, le créer si nécessaire
        $check = $this->db->query("SELECT id FROM services WHERE id = 1 LIMIT 1");
        if (!$check->fetch()) {
            $this->db->exec(
                "INSERT INTO services (id, nom, adresse, horaires_ouverture, horaires_fermeture, statut)
                 VALUES (1, 'Hôpital', '', '08:00:00', '18:00:00', 'actif')"
            );
        }

        $stmt = $this->db->prepare(
            "UPDATE services
             SET horaires_ouverture = :ouv,
                 horaires_fermeture = :fer,
                 pause_debut        = :pd,
                 pause_fin          = :pf
             WHERE id = 1"
        );
        return $stmt->execute([
            ':ouv' => $heureDebut,
            ':fer' => $heureFin,
            ':pd'  => $pauseDebut ?: null,
            ':pf'  => $pauseFin   ?: null,
        ]);
    }

    /**
     * Crée ou met à jour la configuration de l'hôpital.
     * On ne conserve toujours qu'une seule ligne.
     *
     * @param array $data  Clés : nom_hopital, adresse, telephone, email, logo_path
     * @return bool
     */
    public function sauvegarder(array $data): bool {
        $existing = $this->getData();

        if ($existing) {
            $sql = "UPDATE configuration_hopital
                       SET nom_hopital      = :nom_hopital,
                           adresse          = :adresse,
                           telephone        = :telephone,
                           email            = :email,
                           logo_path        = :logo_path,
                           setup_completed  = 1
                     WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':nom_hopital' => $data['nom_hopital'] ?? '',
                ':adresse'     => $data['adresse']     ?? null,
                ':telephone'   => $data['telephone']   ?? null,
                ':email'       => $data['email']       ?? null,
                ':logo_path'   => $data['logo_path']   ?? null,
                ':id'          => $existing['id'],
            ]);
        } else {
            $sql = "INSERT INTO configuration_hopital
                        (nom_hopital, adresse, telephone, email, logo_path, setup_completed)
                    VALUES
                        (:nom_hopital, :adresse, :telephone, :email, :logo_path, 1)";
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                ':nom_hopital' => $data['nom_hopital'] ?? '',
                ':adresse'     => $data['adresse']     ?? null,
                ':telephone'   => $data['telephone']   ?? null,
                ':email'       => $data['email']       ?? null,
                ':logo_path'   => $data['logo_path']   ?? null,
            ]);
        }
    }
}