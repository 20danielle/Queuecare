<?php
/**
 * UtilisateurModel.php - Modèle pour la gestion des utilisateurs
 */

require_once __DIR__ . '/../config/database.php';

class UtilisateurModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        $this->ensureActiveSessionColumns();
    }

    private function ensureActiveSessionColumns(): void {
        $columns = [
            'active_session_token' => "ALTER TABLE utilisateurs ADD COLUMN active_session_token VARCHAR(128) NULL AFTER derniere_connexion",
            'active_session_at' => "ALTER TABLE utilisateurs ADD COLUMN active_session_at DATETIME NULL AFTER active_session_token",
        ];

        foreach ($columns as $column => $sql) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'utilisateurs'
                   AND COLUMN_NAME = :column"
            );
            $stmt->execute([':column' => $column]);
            if ((int)$stmt->fetchColumn() === 0) {
                $this->db->exec($sql);
            }
        }
    }

    /**
     * Authentifie un utilisateur par email et mot de passe
     */
    public function authentifier($email, $password) {
        $sql = "SELECT * FROM utilisateurs WHERE email = :email AND statut = 'actif'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['mot_de_passe'])) {
            unset($user['mot_de_passe']);
            return $user;
        }

        return false;
    }

    /**
     * Récupère un utilisateur par son ID
     */
    public function findById($id) {
        $sql = "SELECT id, email, nom, role, medecin_id, gestionnaire_id, statut
                FROM utilisateurs
                WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Trouve un utilisateur par email
     */
    public function findByEmail($email) {
        $sql = "SELECT id, email, nom, role, medecin_id, gestionnaire_id, statut
                FROM utilisateurs
                WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crée un nouvel utilisateur en hachant le mot de passe.
     * Utilisé par creerAdmin() et creerParAdmin() — le mot de passe
     * est fourni en clair et haché ici.
     */
    public function creer($data) {
        $sql = "INSERT INTO utilisateurs (email, mot_de_passe, role, nom, medecin_id, gestionnaire_id, statut)
                VALUES (:email, :password, :role, :nom, :medecin_id, :gestionnaire_id, 'actif')";

        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':email'          => $data['email'],
            ':password'       => $hashedPassword,
            ':role'           => $data['role'],
            ':nom'            => $data['nom'],
            ':medecin_id'     => $data['medecin_id']     ?? null,
            ':gestionnaire_id'=> $data['gestionnaire_id'] ?? null,
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Crée un utilisateur avec un mot de passe déjà haché.
     * À utiliser quand le hash a déjà été produit ailleurs
     * (inscription médecin / gestionnaire via leur propre formulaire).
     *
     * @param array $data  Clés requises : email, mot_de_passe (hash bcrypt),
     *                     role, nom. Optionnelles : medecin_id, gestionnaire_id.
     * @return int|false  ID inséré ou false
     */
    public function creerAvecHashExistant(array $data) {
        $sql = "INSERT INTO utilisateurs (email, mot_de_passe, role, nom, medecin_id, gestionnaire_id, statut)
                VALUES (:email, :mot_de_passe, :role, :nom, :medecin_id, :gestionnaire_id, 'actif')";

        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute([
            ':email'          => $data['email'],
            ':mot_de_passe'   => $data['mot_de_passe'],   // hash bcrypt déjà prêt
            ':role'           => $data['role'],
            ':nom'            => $data['nom'],
            ':medecin_id'     => $data['medecin_id']      ?? null,
            ':gestionnaire_id'=> $data['gestionnaire_id'] ?? null,
        ]);

        return $result ? $this->db->lastInsertId() : false;
    }

    /**
     * Crée le compte administrateur (directeur).
     * Vérifie d'abord qu'aucun admin n'existe déjà.
     *
     * @param array $data  Clés requises : email, password, nom
     * @return int|false  ID créé ou false
     */
    public function creerAdmin(array $data) {
        if ($this->adminExiste()) {
            return false;
        }
        return $this->creer([
            'email'    => $data['email'],
            'password' => $data['password'],
            'nom'      => $data['nom'],
            'role'     => 'admin',
        ]);
    }

    /**
     * Vérifie si un admin existe déjà dans la base
     */
    public function adminExiste(): bool {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM utilisateurs WHERE role = 'admin'"
        );
        $stmt->execute();
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Liste tous les utilisateurs d'un rôle donné (gestionnaire ou medecin)
     *
     * @param string $role
     * @return array
     */
    public function listerParRole(string $role): array {
        $sql = "SELECT id, email, nom, role, medecin_id, gestionnaire_id, statut, created_at
                FROM utilisateurs
                WHERE role = :role
                ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':role' => $role]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crée un utilisateur gestionnaire ou médecin depuis l'interface admin.
     * Le mot de passe initial est généré ici et retourné en clair pour être
     * communiqué à la personne concernée.
     *
     * @param array $data  Clés : email, nom, role ('gestionnaire'|'medecin'),
     *                            medecin_id (optionnel), gestionnaire_id (optionnel)
     * @return array|false ['id' => int, 'password_clair' => string] ou false
     */
    public function creerParAdmin(array $data) {
        if ($this->emailExiste($data['email'])) {
            return false;
        }

        // Génère un mot de passe initial aléatoire de 10 caractères
        $passwordClair = bin2hex(random_bytes(5)); // ex: "a3f9b2c1d4"

        $id = $this->creer([
            'email'           => $data['email'],
            'password'        => $passwordClair,
            'nom'             => $data['nom'],
            'role'            => $data['role'],
            'medecin_id'      => $data['medecin_id']      ?? null,
            'gestionnaire_id' => $data['gestionnaire_id'] ?? null,
        ]);

        if (!$id) {
            return false;
        }

        return ['id' => $id, 'password_clair' => $passwordClair];
    }

    /**
     * Met à jour le statut d'un utilisateur
     */
    public function mettreAJourStatut($id, $statut) {
        $sql = "UPDATE utilisateurs SET statut = :statut WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':statut' => $statut, ':id' => $id]);
    }

    /**
     * Vérifie si un email existe déjà
     */
    public function emailExiste($email): bool {
        $sql = "SELECT COUNT(*) FROM utilisateurs WHERE email = :email";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':email' => $email]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Met à jour la dernière connexion
     */
    public function updateDerniereConnexion($id) {
        $sql = "UPDATE utilisateurs SET derniere_connexion = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function ouvrirSessionUnique(int $id): string {
        $token = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare(
            "UPDATE utilisateurs
             SET active_session_token = :token, active_session_at = NOW(), derniere_connexion = NOW()
             WHERE id = :id"
        );
        $stmt->execute([':token' => $token, ':id' => $id]);
        return $token;
    }

    public function sessionTokenValide(int $id, string $token): bool {
        if ($token === '') return false;
        $stmt = $this->db->prepare(
            "SELECT COUNT(*)
             FROM utilisateurs
             WHERE id = :id
               AND statut = 'actif'
               AND active_session_token = :token"
        );
        $stmt->execute([':id' => $id, ':token' => $token]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function fermerSessionUnique(int $id, ?string $token = null): void {
        $sql = "UPDATE utilisateurs SET active_session_token = NULL, active_session_at = NULL WHERE id = :id";
        $params = [':id' => $id];
        if ($token !== null && $token !== '') {
            $sql .= " AND active_session_token = :token";
            $params[':token'] = $token;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    public function mettreAJourLangue(int $id, string $langue): bool {
        $langue = in_array($langue, ['fr','en']) ? $langue : 'fr';
        $stmt = $this->db->prepare("UPDATE utilisateurs SET langue = :langue WHERE id = :id");
        return $stmt->execute([':langue' => $langue, ':id' => $id]);
    }

    /**
     * Désactive un utilisateur
     */
    public function desactiver($id) {
        return $this->mettreAJourStatut($id, 'inactif');
    }

    /**
     * Modifie le mot de passe d'un utilisateur (haché ici à partir du mot
     * de passe en clair). Utilisé par le flux "mot de passe oublié" et
     * tout changement de mot de passe initié par l'utilisateur lui-même.
     */
    public function modifierMotDePasse($id, string $nouveauMotDePasseClair): bool {
        $sql = "UPDATE utilisateurs SET mot_de_passe = :mdp WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':mdp' => password_hash($nouveauMotDePasseClair, PASSWORD_DEFAULT),
            ':id'  => $id,
        ]);
    }
}
