<?php
/**
 * models/MedecinModel.php
 * Modèle MVC — Médecin (Version mono-service)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/NotificationModel.php';

class MedecinModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /* ════════════════════════════════════════════════════════
       AUTHENTIFICATION & COMPTE (plus de service_id)
    ════════════════════════════════════════════════════════ */

    public function emailExiste(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM medecins WHERE email = :e');
        $stmt->execute([':e' => strtolower(trim($email))]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function telephoneExiste(string $tel): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM medecins WHERE telephone = :t');
        $stmt->execute([':t' => trim($tel)]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function creer(array $d): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO medecins (nom, prenom, specialite, telephone, email, password, statut, photo)
             VALUES (:nom, :prenom, :specialite, :telephone, :email, :password, "disponible", :photo)'
        );
        return $stmt->execute([
            ':nom'        => htmlspecialchars(trim($d['nom'])),
            ':prenom'     => htmlspecialchars(trim($d['prenom'])),
            ':specialite' => htmlspecialchars(trim($d['specialite'])),
            ':telephone'  => trim($d['telephone']),
            ':email'      => strtolower(trim($d['email'])),
            ':password'   => $d['password'],  // hash bcrypt fourni par le controller
            ':photo'      => $d['photo'] ?? null,
        ]);
    }

    public function dernierID(): int
    {
        return (int)$this->db->lastInsertId();
    }

    public function affecterSousService(int $medecinId, int $ssId): bool
    {
        $stmt = $this->db->prepare(
            'INSERT INTO medecin_sous_service (medecin_id, sous_service_id, date_affectation)
             VALUES (:mid, :ssid, CURDATE())
             ON DUPLICATE KEY UPDATE date_affectation = date_affectation'
        );
        return $stmt->execute([':mid' => $medecinId, ':ssid' => $ssId]);
    }

    public function trouverParEmail(string $email)
    {
        $stmt = $this->db->prepare('SELECT * FROM medecins WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => strtolower(trim($email))]);
        return $stmt->fetch();
    }

    public function trouverParId(int $id)
    {
        $stmt = $this->db->prepare('SELECT * FROM medecins WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function getSousServiceMedecin(int $medecinId)
    {
        $stmt = $this->db->prepare(
            'SELECT ss.id AS ss_id, ss.nom AS ss_nom, ss.duree_estimee,
                    ss.capacite_horaire, ss.qr_code,
                    s.id AS service_id, s.nom AS service_nom, s.adresse
             FROM medecin_sous_service mss
             JOIN sous_services ss ON ss.id = mss.sous_service_id
             JOIN services s ON s.id = ss.service_id
             WHERE mss.medecin_id = :mid
             LIMIT 1'
        );
        $stmt->execute([':mid' => $medecinId]);
        return $stmt->fetch();
    }

    /**
     * Sélectionne le médecin le moins chargé pour un sous-service et une date donnée.
     * Sert de règle commune pour les consultations créées via QR, web ou rendez-vous.
     */
    public function choisirMedecinMoinsOccupe(int $ssId, string $date): int
    {
        $stmt = $this->db->prepare(
            'SELECT m.id,
                    COALESCE(occ.nb, 0) AS nb_consultations
             FROM medecins m
             JOIN medecin_sous_service mss ON mss.medecin_id = m.id
             LEFT JOIN (
                 SELECT medecin_id, COUNT(*) AS nb
                 FROM consultations
                 WHERE sous_service_id = :ss1
                   AND DATE(COALESCE(heure_passage_estimee, heure_emission)) = :date
                   AND medecin_id IS NOT NULL
                   AND statut IN ("en_attente","confirme","en_cours","en_pause")
                 GROUP BY medecin_id
             ) occ ON occ.medecin_id = m.id
             WHERE mss.sous_service_id = :ss2
               AND m.statut = "disponible"
             ORDER BY COALESCE(occ.nb, 0) ASC, m.id ASC
             LIMIT 1'
        );
        $stmt->execute([
            ':ss1' => $ssId,
            ':ss2' => $ssId,
            ':date' => $date,
        ]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : 0;
    }

    /**
     * Affecte automatiquement les consultations du jour sans médecin à un médecin disponible.
     * Utile pour rattraper les tickets créés avant l'unification du flux.
     */
    public function affecterConsultationsSansMedecinDuJour(int $ssId, ?string $date = null): int
    {
        $date = $date ?: date('Y-m-d');

        $stmt = $this->db->prepare(
            'SELECT id
             FROM consultations
             WHERE sous_service_id = :ss
               AND medecin_id IS NULL
               AND DATE(COALESCE(heure_passage_estimee, heure_emission)) = :date
               AND statut IN ("en_attente","confirme","en_cours","en_pause")
             ORDER BY heure_emission ASC, id ASC'
        );
        $stmt->execute([
            ':ss' => $ssId,
            ':date' => $date,
        ]);

        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) {
            return 0;
        }

        $assigned = 0;
        foreach ($ids as $consultationId) {
            $medecinId = $this->choisirMedecinMoinsOccupe($ssId, $date);
            if (!$medecinId) {
                break;
            }

            $upd = $this->db->prepare(
                'UPDATE consultations SET medecin_id = :mid WHERE id = :id AND medecin_id IS NULL'
            );
            $upd->execute([
                ':mid' => $medecinId,
                ':id' => (int)$consultationId,
            ]);

            if ($upd->rowCount() > 0) {
                $assigned++;
            }
        }

        return $assigned;
    }

    /**
     * Horaires du service associé à un sous-service (identique à GestionnaireModel)
     */
    public function getServiceHoraires(int $ssId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.horaires_ouverture, s.horaires_fermeture,
                    s.pause_debut, s.pause_fin, s.jours_fermeture
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE ss.id = :ss LIMIT 1'
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'horaires_ouverture' => '08:00:00',
            'horaires_fermeture' => '18:00:00',
            'pause_debut'        => null,
            'pause_fin'          => null,
            'jours_fermeture'    => '',
        ];
    }

    /* ════════════════════════════════════════════════════════
       LISTES POUR FORMULAIRES (mono-service)
    ════════════════════════════════════════════════════════ */

    public function getSousServicesActifs(): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, nom, duree_estimee, capacite_horaire
             FROM sous_services
             WHERE statut = "actif"
             ORDER BY nom'
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function trouverSousServiceParId(int $id)
    {
        $stmt = $this->db->prepare(
            'SELECT id, nom, statut FROM sous_services WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    /* ════════════════════════════════════════════════════════
       DASHBOARD MÉDECIN
    ════════════════════════════════════════════════════════ */

    public function statsJour(int $ssId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
               COUNT(*) AS total,
               SUM(statut = "traite") AS traitees,
               SUM(statut IN ("en_attente","confirme")) AS en_attente,
               SUM(statut = "absent") AS absentes,
               SUM(statut = "annule") AS annulees,
               COALESCE(ROUND(AVG(
                 CASE WHEN heure_debut_reelle IS NOT NULL
                       AND heure_fin_reelle IS NOT NULL
                      THEN TIMESTAMPDIFF(SECOND, heure_debut_reelle, heure_fin_reelle)
                 END
               )), 0) AS duree_moy_sec
             FROM consultations
             WHERE sous_service_id = :ss
               AND DATE(heure_emission) = CURDATE()'
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Consultations du jour - basé sur heure_passage_estimee
     */
    public function consultationsDuJour(int $ssId, int $medecinId): array
    {
        // Auto-migration : ajoute priorite_retour si la colonne n'existe pas encore
        $check = $this->db->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'consultations'
               AND COLUMN_NAME = 'priorite_retour'"
        );
        if ((int)$check->fetchColumn() === 0) {
            $this->db->exec(
                "ALTER TABLE consultations
                 ADD COLUMN priorite_retour tinyint(1) NOT NULL DEFAULT 0
                 AFTER duree_pause_cumulee"
            );
        }

        $stmt = $this->db->prepare(
            'SELECT c.id, c.patient_id, c.rang, c.statut, c.mode_prise,
                    c.heure_passage_estimee, c.heure_debut_reelle, c.heure_fin_reelle, c.motif,
                    c.heure_pause, c.motif_pause, c.priorite_retour,
                    CASE WHEN c.statut = "en_pause" AND c.heure_pause IS NOT NULL
                         THEN TIMESTAMPDIFF(SECOND, c.heure_pause, NOW())
                         ELSE 0 END AS secondes_en_pause,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.telephone, p.token_fcm
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             WHERE c.sous_service_id = :ss
               AND c.medecin_id = :mid
               AND DATE(c.heure_passage_estimee) = CURDATE()
               AND c.statut NOT IN ("annule", "absent")
             ORDER BY
               CASE WHEN c.statut IN ("en_cours","en_pause") THEN 0 ELSE 1 END ASC,
               c.priorite_retour DESC,
               c.rang ASC'
        );
        $stmt->execute([':ss' => $ssId, ':mid' => $medecinId]);
        return $stmt->fetchAll();
    }

    /**
     * Récupère les consultations d'un médecin sur une période donnée (pour le planning)
     */
    public function getConsultationsParPeriode(int $ssId, int $medecinId, string $dateDebut, string $dateFin): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.patient_id, c.rang, c.statut, c.mode_prise, c.duree_estimee,
                    c.heure_passage_estimee, c.heure_debut_reelle, c.heure_fin_reelle, c.motif,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.telephone, p.token_fcm
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             WHERE c.sous_service_id = :ss
               AND c.medecin_id = :mid
               AND DATE(c.heure_passage_estimee) BETWEEN :date_debut AND :date_fin
               AND c.statut NOT IN ("annule", "absent")
             ORDER BY c.heure_passage_estimee ASC'
        );
        $stmt->execute([
            ':ss' => $ssId,
            ':mid' => $medecinId,
            ':date_debut' => $dateDebut,
            ':date_fin' => $dateFin
        ]);
        return $stmt->fetchAll();
    }

    public function planningMedecin(int $medecinId): array
    {
        $stmt = $this->db->prepare(
            'SELECT edt.id, edt.jour, edt.heure_debut, edt.heure_fin,
                    edt.nb_creneaux, ss.nom AS ss_nom, s.nom AS service_nom
             FROM emplois_du_temps edt
             JOIN sous_services ss ON ss.id = edt.sous_service_id
             JOIN services s ON s.id = ss.service_id
             WHERE edt.medecin_id = :mid
               AND edt.jour BETWEEN
                   DATE(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY))
                   AND DATE(DATE_ADD(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), INTERVAL 6 DAY))
             ORDER BY edt.jour, edt.heure_debut'
        );
        $stmt->execute([':mid' => $medecinId]);
        return $stmt->fetchAll();
    }

    /* ════════════════════════════════════════════════════════
       ACTIONS SUR LES CONSULTATIONS
    ════════════════════════════════════════════════════════ */

    public function demarrerConsultation(int $consultationId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations
             SET statut = "en_cours", heure_debut_reelle = NOW()
             WHERE id = :id AND statut IN ("en_attente", "confirme")'
        );
        return $stmt->execute([':id' => $consultationId]) && $stmt->rowCount() > 0;
    }

    public function terminerConsultation(int $consultationId): bool
    {
        // Accepte en_cours mais aussi en_attente/confirme si le médecin
        // clique sur Terminer sans avoir démarré explicitement
        // Accepte aussi en_pause (ex: examen révèle fin de consultation)
        $stmt = $this->db->prepare(
            'UPDATE consultations
             SET statut = "traite",
                 heure_debut_reelle = COALESCE(heure_debut_reelle, NOW()),
                 heure_fin_reelle   = NOW(),
                 priorite_retour    = 0,
                 heure_pause        = NULL,
                 motif_pause        = NULL
             WHERE id = :id AND statut IN ("en_attente", "confirme", "en_cours", "en_pause")'
        );
        return $stmt->execute([':id' => $consultationId]) && $stmt->rowCount() > 0;
    }

    public function marquerAbsent(int $consultationId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations 
             SET statut = "absent" 
             WHERE id = :id AND statut IN ("en_attente", "confirme", "en_cours")'
        );
        return $stmt->execute([':id' => $consultationId]);
    }

    /**
     * Met une consultation en_cours en pause (examen externe).
     * Marque priorite_retour=1 pour que le patient revienne en tête de file.
     */
    public function mettreEnPause(int $consultationId, string $motif = ''): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations
             SET statut         = "en_pause",
                 heure_pause    = NOW(),
                 motif_pause    = :motif,
                 priorite_retour = 1
             WHERE id = :id AND statut = "en_cours"'
        );
        return $stmt->execute([':id' => $consultationId, ':motif' => $motif]) && $stmt->rowCount() > 0;
    }

    /**
     * Reprend une consultation en_pause → en_cours.
     * Cumule la durée de la pause dans duree_pause_cumulee.
     * Efface priorite_retour une fois la consultation reprise.
     */
    public function reprendreConsultation(int $consultationId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations
             SET statut               = "en_cours",
                 duree_pause_cumulee  = duree_pause_cumulee + TIMESTAMPDIFF(SECOND, heure_pause, NOW()),
                 heure_pause          = NULL,
                 motif_pause          = NULL,
                 priorite_retour      = 0
             WHERE id = :id AND statut = "en_pause"'
        );
        return $stmt->execute([':id' => $consultationId]) && $stmt->rowCount() > 0;
    }

    /**
     * Marque absent automatiquement les consultations en_attente/confirme
     * dont la précédente du même médecin s'est terminée il y a plus de 10 min,
     * ET les consultations en_pause depuis plus de 30 min.
     * Appelé depuis le controller lors de chaque refresh.
     */
    public function verifierAbsencesAuto(int $medecinId): int
    {
        $affected = 0;

        // 1. en_attente/confirme non démarrées 10 min après fin de la précédente
        $stmt = $this->db->prepare(
            'UPDATE consultations c
             JOIN (
               SELECT MAX(heure_fin_reelle) AS derniere_fin
               FROM consultations
               WHERE medecin_id = :mid
                 AND statut = "traite"
                 AND CAST(heure_emission AS DATE) = CURDATE()
                 AND heure_fin_reelle IS NOT NULL
             ) fin ON 1=1
             SET c.statut = "absent"
             WHERE c.medecin_id = :mid2
               AND c.statut IN ("en_attente","confirme")
               AND CAST(c.heure_emission AS DATE) = CURDATE()
               AND c.heure_debut_reelle IS NULL
               AND fin.derniere_fin IS NOT NULL
               AND TIMESTAMPDIFF(MINUTE, fin.derniere_fin, NOW()) >= 10'
        );
        $stmt->execute([':mid' => $medecinId, ':mid2' => $medecinId]);
        $affected += $stmt->rowCount();

        // 2. en_pause depuis plus de 30 minutes → absent
        $stmt2 = $this->db->prepare(
            'UPDATE consultations
             SET statut = "absent"
             WHERE medecin_id = :mid
               AND statut = "en_pause"
               AND heure_pause IS NOT NULL
               AND TIMESTAMPDIFF(MINUTE, heure_pause, NOW()) >= 30'
        );
        $stmt2->execute([':mid' => $medecinId]);
        $affected += $stmt2->rowCount();

        return $affected;
    }

    public function annulerConsultation(int $consultationId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations 
             SET statut = "annule" 
             WHERE id = :id AND statut NOT IN ("traite")'
        );
        return $stmt->execute([':id' => $consultationId]);
    }

    /**
     * Annule les consultations du jour et les reporte au lendemain,
     * en priorité (rangs 1, 2, 3…) et dans le même ordre qu'aujourd'hui.
     */
    public function annulerToutesConsultations(int $medecinId): bool
    {
        try {
            $this->db->beginTransaction();
            $notificationsAEnvoyer = [];

            // 1. Récupérer les consultations actives du jour, dans l'ordre
            $stmt = $this->db->prepare(
                'SELECT c.id, c.patient_id, c.rang, c.heure_passage_estimee, c.duree_estimee,
                        p.token_fcm
                 FROM consultations c
                 JOIN patients p ON p.id = c.patient_id
                 WHERE medecin_id = :mid
                   AND DATE(COALESCE(heure_passage_estimee, heure_emission)) = CURDATE()
                   AND statut NOT IN ("traite", "annule", "absent")
                 ORDER BY rang ASC, heure_passage_estimee ASC'
            );
            $stmt->execute([':mid' => $medecinId]);
            $consultations = $stmt->fetchAll();

            if (empty($consultations)) {
                $this->db->commit();
                return true;
            }

            // 2. Calculer le rang max déjà existant pour demain
            $stmtRang = $this->db->prepare(
                'SELECT COALESCE(MAX(rang), 0) AS max_rang
                 FROM consultations
                 WHERE medecin_id = :mid
                   AND DATE(COALESCE(heure_passage_estimee, heure_emission)) = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
                   AND statut NOT IN ("annule", "absent")'
            );
            $stmtRang->execute([':mid' => $medecinId]);
            $rangBase = (int)$stmtRang->fetchColumn(); // 0 si aucune consultation demain

            // 3. Heure de début pour demain : même heure de début qu'aujourd'hui ou 08:00
            $stmtHeure = $this->db->prepare(
                'SELECT MIN(heure_passage_estimee) AS premiere_heure
                 FROM consultations
                 WHERE medecin_id = :mid
                   AND DATE(heure_passage_estimee) = CURDATE()
                   AND statut NOT IN ("traite","annule","absent")
                   AND heure_passage_estimee IS NOT NULL'
            );
            $stmtHeure->execute([':mid' => $medecinId]);
            $premiereHeure = $stmtHeure->fetchColumn();
            $heureBase = $premiereHeure
                ? date('H:i:s', strtotime($premiereHeure))
                : '08:00:00';
            $demainBase = date('Y-m-d') . ' ' . $heureBase;
            // On utilise demain
            $demainBase = date('Y-m-d', strtotime('+1 day')) . ' ' . $heureBase;

            // 4. Reporter chaque consultation dans l'ordre
            $stmtUpdate = $this->db->prepare(
                'UPDATE consultations
                 SET statut               = "en_attente",
                     rang                 = :nouveau_rang,
                     heure_passage_estimee = :nouvelle_heure,
                     heure_debut_reelle   = NULL,
                     heure_fin_reelle     = NULL
                 WHERE id = :id'
            );

            foreach ($consultations as $i => $c) {
                $nouveauRang = $rangBase + $i + 1;
                // Décaler l'heure selon la durée estimée (défaut 30 min = 1800s)
                $duree = $c['duree_estimee'] ?? 1800;
                $offsetSecondes = $i * $duree;
                $nouvelleHeure  = date('Y-m-d H:i:s', strtotime($demainBase) + $offsetSecondes);

                $stmtUpdate->execute([
                    ':nouveau_rang'   => $nouveauRang,
                    ':nouvelle_heure' => $nouvelleHeure,
                    ':id'             => $c['id'],
                ]);

                $notificationsAEnvoyer[] = [
                    'patient_id' => (int)$c['patient_id'],
                    'consultation_id' => (int)$c['id'],
                    'token_fcm'  => $c['token_fcm'] ?? null,
                    'type'       => 'DECALAGE',
                    'contenu'    => sprintf(
                        'Votre rendez-vous a été reporté au %s à %s.',
                        date('d/m/Y', strtotime($nouvelleHeure)),
                        date('H:i', strtotime($nouvelleHeure))
                    ),
                ];
            }

            $this->db->commit();
            foreach ($notificationsAEnvoyer as $notification) {
                $this->notifierPatient(
                    $notification['patient_id'],
                    $notification['type'],
                    $notification['contenu'],
                    $notification['consultation_id'],
                    $notification['token_fcm']
                );
            }
            return true;

        } catch (\PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }

    public function majStatutConsultation(int $id, string $statut): bool
    {
        $allowed = ['en_attente', 'confirme', 'en_cours', 'traite', 'annule', 'absent'];
        if (!in_array($statut, $allowed)) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE consultations SET statut = :s WHERE id = :id');
        return $stmt->execute([':s' => $statut, ':id' => $id]);
    }

    /* ════════════════════════════════════════════════════════
       GESTION DU PROFIL
    ════════════════════════════════════════════════════════ */

    public function mettreAJourProfil(int $id, string $nom, string $prenom, string $telephone): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE medecins SET nom = :nom, prenom = :prenom, telephone = :telephone WHERE id = :id'
        );
        return $stmt->execute([
            ':nom' => htmlspecialchars(trim($nom)),
            ':prenom' => htmlspecialchars(trim($prenom)),
            ':telephone' => trim($telephone),
            ':id' => $id
        ]);
    }

    public function mettreAJourEmail(int $id, string $email): bool
    {
        $stmt = $this->db->prepare('UPDATE medecins SET email = :email WHERE id = :id');
        return $stmt->execute([
            ':email' => strtolower(trim($email)),
            ':id' => $id
        ]);
    }

    public function mettreAJourMotDePasse(int $id, string $password): bool
    {
        $stmt = $this->db->prepare('UPDATE medecins SET password = :password WHERE id = :id');
        return $stmt->execute([
            ':password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            ':id' => $id
        ]);
    }

    public function mettreAJourPhoto(int $id, ?string $photoPath): bool
    {
        $stmt = $this->db->prepare('UPDATE medecins SET photo = :photo WHERE id = :id');
        return $stmt->execute([
            ':photo' => $photoPath,
            ':id' => $id
        ]);
    }

    public function verifierMotDePasse(int $id, string $password): bool
    {
        $stmt = $this->db->prepare('SELECT password FROM medecins WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $hash = $stmt->fetchColumn();
        return password_verify($password, $hash);
    }

    /* ════════════════════════════════════════════════════════
       JOURS DE TRAVAIL DU MÉDECIN
    ════════════════════════════════════════════════════════ */

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

    public function sauvegarderJoursTravailMedecin(int $medecinId, array $jours): bool
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM medecin_jours_travail WHERE medecin_id = :mid');
            $stmt->execute([':mid' => $medecinId]);

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

    /**
     * Historique paginé des consultations du médecin (tous statuts, toutes dates passées)
     */
    public function historiquePagine(int $medecinId, int $page, int $perPage, string $statut = '', string $dateDebut = '', string $dateFin = ''): array
    {
        $conditions = ['c.medecin_id = :mid', 'DATE(c.heure_passage_estimee) < CURDATE() OR c.statut IN ("traite","annule","absent")'];
        $params = [':mid' => $medecinId];

        if ($statut !== '') {
            $conditions[] = 'c.statut = :statut';
            $params[':statut'] = $statut;
        }
        if ($dateDebut !== '') {
            $conditions[] = 'DATE(c.heure_passage_estimee) >= :ddebut';
            $params[':ddebut'] = $dateDebut;
        }
        if ($dateFin !== '') {
            $conditions[] = 'DATE(c.heure_passage_estimee) <= :dfin';
            $params[':dfin'] = $dateFin;
        }

        $where = implode(' AND ', $conditions);

        // Compter le total
        $stmtCount = $this->db->prepare("SELECT COUNT(*) FROM consultations c WHERE $where");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare(
            "SELECT c.id, c.rang, c.statut, c.mode_prise, c.motif,
                    c.heure_passage_estimee, c.heure_debut_reelle, c.heure_fin_reelle,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.telephone
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             WHERE $where
             ORDER BY c.heure_passage_estimee DESC
             LIMIT :limit OFFSET :offset"
        );
        foreach ($params as $key => $val) {
            $type = in_array($key, [':limit', ':offset']) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($key, $val, $type);
        }
        $stmt->execute();

        return [
            'data'       => $stmt->fetchAll(),
            'total'      => $total,
            'page'       => $page,
            'per_page'   => $perPage,
            'last_page'  => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /* ════════════════════════════════════════════════════════
       PROCHAIN RDV – Planification depuis une consultation
    ════════════════════════════════════════════════════════ */

    /**
     * Auto-migration : ajoute prochain_rdv_id si absent.
     */
    /**
     * Retourne la durée estimée d'une consultation pour un sous-service (en secondes).
     */
    public function getDureeEstimeeParSS(int $ssId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(duree_estimee, duree_rdv_defaut, 1800) FROM sous_services WHERE id = :id'
        );
        $stmt->execute([':id' => $ssId]);
        return (int)($stmt->fetchColumn() ?: 1800);
    }

    /**
     * Calcule l'heure de début estimée pour le prochain patient d'un jour donné.
     * - 1er patient → debut = heure d'ouverture du service
     * - Patient suivant → debut = heure_passage_estimee du dernier + duree_estimee du dernier
     *
     * @return array{heure_debut: string, heure_fin: string}  (format 'Y-m-d H:i:s')
     */
    public function calculerHeurePassageEstimee(int $ssId, string $date): array
    {
        $dureeEstimee = $this->getDureeEstimeeParSS($ssId);

        // Horaires d'ouverture
        $horaires = $this->getServiceHoraires($ssId);
        $heureOuverture = $horaires['horaires_ouverture'] ?? '08:00:00';

        // Dernière consultation planifiée ce jour
        $stmt = $this->db->prepare(
            'SELECT heure_passage_estimee, heure_debut_reelle, duree_estimee
             FROM consultations
             WHERE sous_service_id = :ss
               AND DATE(heure_passage_estimee) = :date
               AND statut NOT IN ("annule","absent")
             ORDER BY heure_passage_estimee DESC
             LIMIT 1'
        );
        $stmt->execute([':ss' => $ssId, ':date' => $date]);
        $last = $stmt->fetch();

        if ($last && $last['heure_passage_estimee']) {
            $dureePrec = (int)($last['duree_estimee'] ?: $dureeEstimee);

            // Si la consultation précédente a déjà démarré (heure_debut_reelle
            // renseignée), on se base sur son heure de DÉBUT RÉELLE + durée
            // moyenne, plus fiable que l'heure estimée car elle reflète le
            // retard ou l'avance effectif du médecin. Sinon, on garde l'heure
            // de début ESTIMÉE comme base de calcul.
            $heureBase = $last['heure_debut_reelle'] ?: $last['heure_passage_estimee'];
            $heureDebutTs = strtotime($heureBase) + $dureePrec;
        } else {
            $heureDebutTs = strtotime($date . ' ' . $heureOuverture);
        }

        return [
            'heure_debut' => date('Y-m-d H:i:s', $heureDebutTs),
            'heure_fin'   => date('Y-m-d H:i:s', $heureDebutTs + $dureeEstimee),
        ];
    }

    private function migrerColonneProchainRdv(): void
    {
        $check = $this->db->query(
            "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'consultations'
               AND COLUMN_NAME  = 'prochain_rdv_id'"
        );
        if ((int)$check->fetchColumn() === 0) {
            $this->db->exec(
                "ALTER TABLE consultations
                 ADD COLUMN prochain_rdv_id int UNSIGNED DEFAULT NULL
                 COMMENT 'ID de la consultation de suivi planifiée par le médecin'
                 AFTER motif"
            );
        }
    }

    /**
     * Crée une nouvelle consultation (prochain RDV) pour un patient existant,
     * à une date/heure précise, et la lie à la consultation source.
     *
     * @param int    $consultationSourceId  Consultation en cours / terminée
     * @param string $dateRdv               Format Y-m-d
     * @param string $heureRdv              Format H:i
     * @param string $motif                 Motif du prochain RDV (optionnel)
     * @return array ['success'=>bool, 'rdv_id'=>int|null, 'message'=>string]
     */
    public function fixerProchainRdv(int $consultationSourceId, string $dateRdv, string $heureRdv, string $motif = ''): array
    {
        $this->migrerColonneProchainRdv();

        // 1. Récupérer la consultation source
        $stmt = $this->db->prepare(
            'SELECT c.*, p.nom AS patient_nom, p.prenom AS patient_prenom, p.token_fcm
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             WHERE c.id = :id'
        );
        $stmt->execute([':id' => $consultationSourceId]);
        $source = $stmt->fetch();

        if (!$source) {
            return ['success' => false, 'rdv_id' => null, 'message' => 'Consultation source introuvable.'];
        }

        // 2. Vérifier qu'il n'y a pas déjà un RDV planifié pour ce patient ce jour-là
        $stmt2 = $this->db->prepare(
            'SELECT id FROM consultations
             WHERE patient_id      = :pid
               AND sous_service_id = :ss
               AND DATE(heure_passage_estimee) = :date
               AND statut NOT IN ("annule", "absent")'
        );
        $stmt2->execute([
            ':pid'  => $source['patient_id'],
            ':ss'   => $source['sous_service_id'],
            ':date' => $dateRdv,
        ]);
        if ($stmt2->fetch()) {
            return ['success' => false, 'rdv_id' => null, 'message' => 'Ce patient a déjà un rendez-vous ce jour-là.'];
        }

        // 3. Calculer le rang pour ce jour futur
        $stmtRang = $this->db->prepare(
            'SELECT COALESCE(MAX(rang), 0) + 1
             FROM consultations
             WHERE sous_service_id = :ss
               AND DATE(heure_passage_estimee) = :date
               AND statut NOT IN ("annule", "absent")'
        );
        $stmtRang->execute([':ss' => $source['sous_service_id'], ':date' => $dateRdv]);
        $rang = (int)$stmtRang->fetchColumn();

        // 4. Calculer les heures estimées selon la logique séquentielle
        $heures = $this->calculerHeurePassageEstimee((int)$source['sous_service_id'], $dateRdv);
        $heurePassage = $heures['heure_debut'];
        $dureeEstimee = $this->getDureeEstimeeParSS((int)$source['sous_service_id']);
        $motifPropre  = !empty($motif) ? htmlspecialchars(trim($motif)) : 'Suivi';

        $stmtIns = $this->db->prepare(
            'INSERT INTO consultations
                (patient_id, sous_service_id, medecin_id, statut, rang, mode_prise,
                 heure_emission, heure_passage_estimee, duree_estimee, motif)
             VALUES
                (:patient_id, :ss, :medecin_id, "confirme", :rang, "MANUEL",
                 NOW(), :heure, :duree, :motif)'
        );
        $stmtIns->execute([
            ':patient_id' => $source['patient_id'],
            ':ss'         => $source['sous_service_id'],
            ':medecin_id' => $source['medecin_id'],
            ':rang'        => $rang,
            ':heure'       => $heurePassage,
            ':duree'       => $dureeEstimee,
            ':motif'       => $motifPropre,
        ]);
        $rdvId = (int)$this->db->lastInsertId();

        if (!$rdvId) {
            return ['success' => false, 'rdv_id' => null, 'message' => 'Erreur lors de la création du rendez-vous.'];
        }

        // 5. Lier la consultation source au nouveau RDV
        $stmtLink = $this->db->prepare(
            'UPDATE consultations SET prochain_rdv_id = :rdv WHERE id = :id'
        );
        $stmtLink->execute([':rdv' => $rdvId, ':id' => $consultationSourceId]);

        // 6. Mettre à jour / créer le créneau dans emplois_du_temps
        $heureRdvPourEdt = date('H:i', strtotime($heurePassage));
        $this->reserverCreneauEdt(
            (int)$source['sous_service_id'],
            (int)$source['medecin_id'],
            $dateRdv,
            $heureRdvPourEdt
        );

        $this->notifierPatient(
            (int)$source['patient_id'],
            'CONFIRMATION',
            sprintf(
                'Votre prochain rendez-vous est fixé au %s à %s.',
                date('d/m/Y', strtotime($dateRdv)),
                $heureRdv
            ),
            $rdvId,
            $source['token_fcm'] ?? null
        );

        return [
            'success'     => true,
            'rdv_id'      => $rdvId,
            'message'     => 'Rendez-vous fixé avec succès.',
            'patient_nom' => $source['patient_nom'] . ' ' . $source['patient_prenom'],
            'date_rdv'    => $dateRdv,
            'heure_rdv'   => $heureRdv,
        ];
    }

    /**
     * Notifie un patient via la base de notifications et FCM.
     */
    private function notifierPatient(
        int $patientId,
        string $type,
        string $contenu,
        ?int $consultationId = null,
        ?string $tokenFcm = null
    ): array {
        if ($patientId <= 0) {
            return ['success' => false, 'message' => 'Patient invalide'];
        }

        $notificationModel = new NotificationModel();
        return $notificationModel->enregistrerEtEnvoyer(
            $patientId,
            $type,
            $contenu,
            $consultationId,
            $tokenFcm
        );
    }

    /**
     * Notifie les patients encore en attente après la fin d'une consultation.
     */
    public function notifierPatientsSuivantsApresTerminaison(int $medecinId, int $consultationTermineeId): int
    {
        $affectation = $this->getSousServiceMedecin($medecinId);
        if (!$affectation) {
            return 0;
        }

        $consultations = $this->consultationsDuJour((int)$affectation['ss_id'], $medecinId);
        $envoyees = 0;

        foreach ($consultations as $consultation) {
            if ((int)$consultation['id'] === $consultationTermineeId) {
                continue;
            }

            if (!in_array($consultation['statut'], ['en_attente', 'confirme'], true)) {
                continue;
            }

            $message = sprintf(
                'La consultation précédente vient de se terminer. Si vous êtes le prochain patient, préparez-vous pour un passage estimé à %s.',
                !empty($consultation['heure_passage_estimee'])
                    ? date('H:i', strtotime($consultation['heure_passage_estimee']))
                    : 'bientôt'
            );

            $result = $this->notifierPatient(
                (int)$consultation['patient_id'],
                'AVANCEMENT',
                $message,
                (int)$consultation['id'],
                $consultation['token_fcm'] ?? null
            );

            if (!empty($result['success'])) {
                $envoyees++;
            }
        }

        return $envoyees;
    }

    /**
     * Notifie le patient dont la consultation vient d'être terminée.
     */
    public function notifierConsultationTerminee(int $consultationId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.patient_id, p.token_fcm
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             WHERE c.id = :id'
        );
        $stmt->execute([':id' => $consultationId]);
        $consultation = $stmt->fetch();

        if (!$consultation) {
            return ['success' => false, 'message' => 'Consultation introuvable'];
        }

        return $this->notifierPatient(
            (int)$consultation['patient_id'],
            'INFO',
            'Votre consultation est terminée. Merci de rester attentif aux prochaines notifications.',
            (int)$consultation['id'],
            $consultation['token_fcm'] ?? null
        );
    }

    /**
     * Met à jour nb_creneaux dans emplois_du_temps pour le créneau horaire
     * correspondant, ou crée la ligne si elle n'existe pas encore.
     */
    private function reserverCreneauEdt(int $ssId, int $medecinId, string $date, string $heure): void
    {
        $heureDebut = date('H:00:00', strtotime($heure));
        $heureFin   = date('H:00:00', strtotime($heure . ' +1 hour'));

        // Cherche un créneau existant sur ce médecin / ce jour / cette heure
        $stmt = $this->db->prepare(
            'SELECT id, nb_creneaux FROM emplois_du_temps
             WHERE medecin_id     = :mid
               AND sous_service_id = :ss
               AND jour            = :jour
               AND heure_debut     = :hdebut'
        );
        $stmt->execute([
            ':mid'    => $medecinId,
            ':ss'     => $ssId,
            ':jour'   => $date,
            ':hdebut' => $heureDebut,
        ]);
        $row = $stmt->fetch();

        if ($row) {
            // Incrémenter
            $upd = $this->db->prepare(
                'UPDATE emplois_du_temps
                 SET nb_creneaux = nb_creneaux + 1
                 WHERE id = :id'
            );
            $upd->execute([':id' => $row['id']]);
        } else {
            // Créer
            $ins = $this->db->prepare(
                'INSERT INTO emplois_du_temps
                    (sous_service_id, medecin_id, jour, heure_debut, heure_fin, nb_creneaux)
                 VALUES (:ss, :mid, :jour, :hdebut, :hfin, 1)'
            );
            $ins->execute([
                ':ss'    => $ssId,
                ':mid'   => $medecinId,
                ':jour'  => $date,
                ':hdebut'=> $heureDebut,
                ':hfin'  => $heureFin,
            ]);
        }
    }

    /**
     * Retourne les créneaux disponibles d'un médecin pour une date donnée
     * (heures non encore saturées, basé sur les horaires du service).
     */
    public function getCreneauxDisponibles(int $medecinId, string $date): array
    {
        // Horaires du service (on prend le service lié au médecin)
        $aff = $this->getSousServiceMedecin($medecinId);
        if (!$aff) return [];

        // Horaires d'ouverture du service
        $stmt = $this->db->prepare(
            'SELECT s.horaires_ouverture, s.horaires_fermeture, s.pause_debut, s.pause_fin,
                    ss.capacite_horaire
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE ss.id = :ss'
        );
        $stmt->execute([':ss' => $aff['ss_id']]);
        $svc = $stmt->fetch();

        if (!$svc) return [];

        $ouv   = $svc['horaires_ouverture'] ?? '08:00:00';
        $ferm  = $svc['horaires_fermeture'] ?? '18:00:00';
        $cap   = (int)($svc['capacite_horaire'] ?? 10);

        // Compter les consultations déjà prises par heure ce jour-là pour ce médecin
        $stmtOcc = $this->db->prepare(
            'SELECT HOUR(heure_passage_estimee) AS heure, COUNT(*) AS nb
             FROM consultations
             WHERE medecin_id = :mid
               AND DATE(heure_passage_estimee) = :date
               AND statut NOT IN ("annule", "absent")
             GROUP BY HOUR(heure_passage_estimee)'
        );
        $stmtOcc->execute([':mid' => $medecinId, ':date' => $date]);
        $occupes = [];
        foreach ($stmtOcc->fetchAll() as $r) {
            $occupes[(int)$r['heure']] = (int)$r['nb'];
        }

        $creneaux = [];
        $hOuv  = (int)date('H', strtotime($ouv));
        $hFerm = (int)date('H', strtotime($ferm));

        for ($h = $hOuv; $h < $hFerm; $h++) {
            $pris = $occupes[$h] ?? 0;
            if ($pris < $cap) {
                $label = sprintf('%02d:00', $h);
                $creneaux[] = [
                    'heure'      => $label,
                    'pris'       => $pris,
                    'disponible' => $cap - $pris,
                ];
            }
        }

        return $creneaux;
    }

    /**
     * Évolution des consultations sur les N derniers jours (pour les graphiques)
     * Retourne les données groupées par jour avec le nombre par statut.
     */
    public function statsEvolution(int $medecinId, int $jours = 30): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                DATE(c.heure_passage_estimee) AS jour,
                COUNT(*) AS total,
                SUM(c.statut = 'traite') AS traitees,
                SUM(c.statut = 'absent') AS absentes,
                SUM(c.statut = 'annule') AS annulees
             FROM consultations c
             WHERE c.medecin_id = :mid
               AND DATE(c.heure_passage_estimee) >= DATE_SUB(CURDATE(), INTERVAL :jours DAY)
               AND DATE(c.heure_passage_estimee) <= CURDATE()
             GROUP BY DATE(c.heure_passage_estimee)
             ORDER BY jour ASC"
        );
        $stmt->execute([':mid' => $medecinId, ':jours' => $jours]);
        return $stmt->fetchAll();
    }

    /**
     * Stats globales agrégées sur toute la période (pour les totaux des graphiques)
     */
    public function statsTotalesMedecin(int $medecinId, int $jours = 30): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(statut = 'traite') AS traitees,
                SUM(statut = 'absent') AS absentes,
                SUM(statut = 'annule') AS annulees,
                COALESCE(ROUND(AVG(
                  CASE WHEN heure_debut_reelle IS NOT NULL AND heure_fin_reelle IS NOT NULL
                       THEN TIMESTAMPDIFF(SECOND, heure_debut_reelle, heure_fin_reelle)
                  END
                )), 0) AS duree_moy_sec
             FROM consultations
             WHERE medecin_id = :mid
               AND DATE(heure_passage_estimee) >= DATE_SUB(CURDATE(), INTERVAL :jours DAY)"
        );
        $stmt->execute([':mid' => $medecinId, ':jours' => $jours]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Évolution du temps d'attente moyen par jour pour un médecin/sous-service
     * Temps d'attente = heure_debut_reelle - heure_emission (du ticket)
     */
    public function evolutionTempsAttente(int $sousServiceId, int $jours = 30): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                DATE(c.heure_emission) AS jour,
                ROUND(AVG(
                    CASE WHEN c.heure_debut_reelle IS NOT NULL AND c.statut = 'traite'
                         THEN TIMESTAMPDIFF(MINUTE, c.heure_emission, c.heure_debut_reelle)
                    END
                ), 1) AS attente_moy_min,
                COUNT(CASE WHEN c.statut = 'traite' AND c.heure_debut_reelle IS NOT NULL THEN 1 END) AS nb_mesures
             FROM consultations c
             WHERE c.sous_service_id = :ssid
               AND DATE(c.heure_emission) >= DATE_SUB(CURDATE(), INTERVAL :jours DAY)
               AND DATE(c.heure_emission) <= CURDATE()
             GROUP BY DATE(c.heure_emission)
             ORDER BY jour ASC"
        );
        $stmt->execute([':ssid' => $sousServiceId, ':jours' => $jours]);
        return $stmt->fetchAll();
    }

    /**
     * Temps d'attente moyen global depuis le début pour ce sous-service
     */
    public function tempsAttenteGlobal(int $sousServiceId): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                ROUND(AVG(TIMESTAMPDIFF(MINUTE, c.heure_emission, c.heure_debut_reelle)), 1) AS attente_moy_min,
                MIN(DATE(c.heure_emission)) AS premier_jour,
                COUNT(*) AS nb_mesures,
                ROUND(AVG(CASE WHEN DATE(c.heure_emission) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                               THEN TIMESTAMPDIFF(MINUTE, c.heure_emission, c.heure_debut_reelle) END), 1) AS attente_7j_min,
                ROUND(AVG(CASE WHEN DATE(c.heure_emission) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                               THEN TIMESTAMPDIFF(MINUTE, c.heure_emission, c.heure_debut_reelle) END), 1) AS attente_30j_min
             FROM consultations c
             WHERE c.sous_service_id = :ssid
               AND c.statut = 'traite'
               AND c.heure_debut_reelle IS NOT NULL"
        );
        $stmt->execute([':ssid' => $sousServiceId]);
        return $stmt->fetch() ?: [];
    }
}
