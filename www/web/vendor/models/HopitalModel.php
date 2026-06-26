<?php
/**
 * HopitalModel.php — CRUD sur la table configuration_hopital
 *
 * Schéma réel :
 *   id, nom_hopital, adresse, telephone, email, logo_path,
 *   setup_completed (tinyint), date_installation, date_mise_a_jour
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
