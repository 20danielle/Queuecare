<?php
/**
 * models/PasswordResetModel.php
 * Gère la création et la vérification des codes envoyés par email
 * lors d'une demande de "mot de passe oublié".
 */

require_once __DIR__ . '/../config/database.php';

class PasswordResetModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Invalide tous les codes encore valides pour un email donné.
     * Appelé avant de générer un nouveau code (un seul code actif à la fois).
     */
    public function invaliderCodesExistants(string $email): void {
        $sql = "UPDATE password_resets SET utilise = 1 WHERE email = :email AND utilise = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
    }

    /**
     * Enregistre un nouveau code (haché) pour l'email donné.
     *
     * @param string $email
     * @param string $code   Code en clair (ex: "483920"), haché avant stockage.
     * @return bool
     */
    public function creerCode(string $email, string $code): bool {
        $this->invaliderCodesExistants($email);

        $dureeMin = defined('RESET_CODE_DUREE_MIN') ? RESET_CODE_DUREE_MIN : 15;

        $sql = "INSERT INTO password_resets (email, code_hash, expire_at, utilise)
                VALUES (:email, :code_hash, DATE_ADD(NOW(), INTERVAL :duree MINUTE), 0)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':email'     => $email,
            ':code_hash' => password_hash($code, PASSWORD_DEFAULT),
            ':duree'     => $dureeMin,
        ]);
    }

    /**
     * Vérifie un code saisi par l'utilisateur pour un email donné.
     * Si valide : marque le code comme utilisé et retourne true.
     *
     * @return bool
     */
    public function verifierEtConsommerCode(string $email, string $code): bool {
        $sql = "SELECT id, code_hash FROM password_resets
                WHERE email = :email AND utilise = 0 AND expire_at >= NOW()
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !password_verify($code, $row['code_hash'])) {
            return false;
        }

        $update = $this->db->prepare("UPDATE password_resets SET utilise = 1 WHERE id = :id");
        $update->execute([':id' => $row['id']]);

        return true;
    }

    /**
     * Indique si une demande de code encore valide (non expirée) existe pour cet email.
     * Utilisé pour afficher/masquer le bouton "Renvoyer le code".
     */
    public function aUneDemandeEnCours(string $email): bool {
        $sql = "SELECT COUNT(*) FROM password_resets
                WHERE email = :email AND utilise = 0 AND expire_at >= NOW()";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        return (int) $stmt->fetchColumn() > 0;
    }
}