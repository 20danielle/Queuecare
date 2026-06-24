<?php
/**
 * models/PatientModel.php
 * Modèle pour la gestion des patients
 */

require_once __DIR__ . '/../config/database.php';

class PatientModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /* ═══════════════════════════════════════════════════════════
       CRUD PATIENT
    ═══════════════════════════════════════════════════════════ */

    /**
     * Trouve un patient par son ID
     */
    public function trouverParId(int $id)
    {
        $stmt = $this->db->prepare('SELECT * FROM patients WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /**
     * Trouve un patient par son email
     */
    public function trouverParEmail(string $email)
    {
        $stmt = $this->db->prepare('SELECT * FROM patients WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => strtolower(trim($email))]);
        return $stmt->fetch();
    }

    /**
     * Trouve un patient par son téléphone
     */
    public function trouverParTelephone(string $telephone)
    {
        $stmt = $this->db->prepare('SELECT * FROM patients WHERE telephone = :tel LIMIT 1');
        $stmt->execute([':tel' => trim($telephone)]);
        return $stmt->fetch();
    }

    /**
     * Vérifie si un email existe déjà
     */
    public function emailExiste(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM patients WHERE email = :e');
        $stmt->execute([':e' => strtolower(trim($email))]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Vérifie si un téléphone existe déjà
     */
    public function telephoneExiste(string $telephone): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM patients WHERE telephone = :tel');
        $stmt->execute([':tel' => trim($telephone)]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Crée un nouveau patient
     */
    public function creer(array $data): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO patients (nom, prenom, telephone, email, password, token_fcm, statut)
             VALUES (:nom, :prenom, :telephone, :email, :password, :token_fcm, :statut)'
        );
        
        $success = $stmt->execute([
            ':nom'        => htmlspecialchars(trim($data['nom'])),
            ':prenom'     => htmlspecialchars(trim($data['prenom'])),
            ':telephone'  => trim($data['telephone']),
            ':email'      => strtolower(trim($data['email'])),
            ':password'   => !empty($data['password']) ? password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]) : null,
            ':token_fcm'  => $data['token_fcm'] ?? null,
            ':statut'     => $data['statut'] ?? 'actif'
        ]);
        
        return $success ? (int)$this->db->lastInsertId() : false;
    }

    /**
     * Met à jour un patient existant
     */
    public function mettreAJour(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE patients 
             SET nom = :nom, prenom = :prenom, telephone = :telephone, email = :email,
                 token_fcm = :token_fcm, statut = :statut
             WHERE id = :id'
        );
        
        return $stmt->execute([
            ':id'         => $id,
            ':nom'        => htmlspecialchars(trim($data['nom'])),
            ':prenom'     => htmlspecialchars(trim($data['prenom'])),
            ':telephone'  => trim($data['telephone']),
            ':email'      => strtolower(trim($data['email'])),
            ':token_fcm'  => $data['token_fcm'] ?? null,
            ':statut'     => $data['statut'] ?? 'actif'
        ]);
    }

    /**
     * Met à jour le mot de passe d'un patient
     */
    public function mettreAJourMotDePasse(int $id, string $password): bool
    {
        $stmt = $this->db->prepare('UPDATE patients SET password = :password WHERE id = :id');
        return $stmt->execute([
            ':password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            ':id' => $id
        ]);
    }

    /**
     * Met à jour le token FCM pour les notifications push
     */
    public function mettreAJourTokenFCM(int $id, ?string $tokenFcm): bool
    {
        $stmt = $this->db->prepare('UPDATE patients SET token_fcm = :token WHERE id = :id');
        return $stmt->execute([':token' => $tokenFcm, ':id' => $id]);
    }

    /**
     * Supprime le token FCM d'un patient (désabonnement)
     */
    public function supprimerTokenFCM(int $id): bool
    {
        return $this->mettreAJourTokenFCM($id, null);
    }

    /**
     * Récupère le token FCM d'un patient
     */
    public function getTokenFCM(int $patientId): ?string
    {
        $stmt = $this->db->prepare('SELECT token_fcm FROM patients WHERE id = :id');
        $stmt->execute([':id' => $patientId]);
        $result = $stmt->fetch();
        return $result ? $result['token_fcm'] : null;
    }

    /**
     * Récupère tous les tokens FCM actifs (pour envoi groupé)
     */
    public function getAllActiveTokens(): array
    {
        $stmt = $this->db->prepare(
            'SELECT token_fcm FROM patients WHERE token_fcm IS NOT NULL AND token_fcm != "" AND statut = "actif"'
        );
        $stmt->execute();
        return array_column($stmt->fetchAll(), 'token_fcm');
    }

    /**
     * Récupère les tokens FCM des patients ayant une consultation à une date donnée
     */
    public function getTokensForDate(string $date): array
    {
        $stmt = $this->db->prepare(
            'SELECT DISTINCT p.token_fcm 
             FROM patients p
             JOIN consultations c ON c.patient_id = p.id
             WHERE DATE(c.heure_passage_estimee) = :date
               AND p.token_fcm IS NOT NULL 
               AND p.token_fcm != ""
               AND p.statut = "actif"'
        );
        $stmt->execute([':date' => $date]);
        return array_column($stmt->fetchAll(), 'token_fcm');
    }

    /**
     * Vérifie les identifiants de connexion
     */
    public function verifierConnexion(string $email, string $password)
    {
        $stmt = $this->db->prepare('SELECT * FROM patients WHERE email = :email AND statut = "actif" LIMIT 1');
        $stmt->execute([':email' => strtolower(trim($email))]);
        $patient = $stmt->fetch();
        
        if ($patient && password_verify($password, $patient['password'])) {
            return $patient;
        }
        
        return false;
    }

    /**
     * Crée ou récupère un patient anonyme (sans mot de passe)
     */
    public function trouverOuCreerAnonyme(string $telephone, string $nom, string $prenom): int|false
    {
        // Vérifier si le patient existe déjà
        $patient = $this->trouverParTelephone($telephone);
        if ($patient) {
            return $patient['id'];
        }
        
        // Créer un nouveau patient anonyme
        $email = $telephone . '@patient.queuecare.local';
        
        // S'assurer que l'email est unique
        $counter = 1;
        $originalEmail = $email;
        while ($this->emailExiste($email)) {
            $email = str_replace('@', "_{$counter}@", $originalEmail);
            $counter++;
        }
        
        return $this->creer([
            'nom'       => $nom,
            'prenom'    => $prenom,
            'telephone' => $telephone,
            'email'     => $email,
            'password'  => null,
            'statut'    => 'actif'
        ]);
    }

    /**
     * Récupère les consultations d'un patient
     */
    public function getConsultationsPatient(int $patientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.*, ss.nom as sous_service_nom, s.nom as service_nom,
                    CONCAT(m.prenom, " ", m.nom) as medecin_nom
             FROM consultations c
             JOIN sous_services ss ON ss.id = c.sous_service_id
             JOIN services s ON s.id = ss.service_id
             LEFT JOIN medecins m ON m.id = c.medecin_id
             WHERE c.patient_id = :pid
             ORDER BY c.heure_emission DESC
             LIMIT 50'
        );
        $stmt->execute([':pid' => $patientId]);
        return $stmt->fetchAll();
    }

    /**
     * Récupère les tickets (files d'attente) d'un patient
     */
    public function getTicketsPatient(int $patientId): array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, ss.nom as sous_service_nom
             FROM tickets t
             JOIN qr_codes qc ON qc.id = t.qr_code_id
             JOIN sous_services ss ON ss.id = qc.sous_service_id
             WHERE t.patient_id = :pid
             ORDER BY t.created_at DESC
             LIMIT 20'
        );
        $stmt->execute([':pid' => $patientId]);
        return $stmt->fetchAll();
    }

    /**
     * Supprime un patient (soft delete via statut)
     */
    public function desactiver(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE patients SET statut = "inactif" WHERE id = :id');
        return $stmt->execute([':id' => $id]);
    }
}