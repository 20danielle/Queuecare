<?php
/**
 * models/GestionnaireModel.php
 * Modèle MVC — Gestionnaire
 */

require_once __DIR__ . '/../config/database.php';

class GestionnaireModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /* ════════════════════════════════════════════════════════
       AUTHENTIFICATION & COMPTE
    ════════════════════════════════════════════════════════ */

    public function emailExiste(string $email): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM gestionnaires WHERE email = :e');
        $stmt->execute([':e' => strtolower(trim($email))]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function telephoneExiste(string $telephone): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM gestionnaires WHERE telephone = :t');
        $stmt->execute([':t' => trim($telephone)]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function creer(array $d): int|false
    {
        $stmt = $this->db->prepare(
            'INSERT INTO gestionnaires (nom, telephone, email, password, sous_service_id, langue)
             VALUES (:nom, :telephone, :email, :password, :sous_service_id, :langue)'
        );
        $ok = $stmt->execute([
            ':nom'             => htmlspecialchars(trim($d['nom'])),
            ':telephone'       => trim($d['telephone']),
            ':email'           => strtolower(trim($d['email'])),
            ':password'        => password_hash($d['password'], PASSWORD_BCRYPT, ['cost' => 12]),
            ':sous_service_id' => (int)$d['sous_service_id'],
            ':langue'          => in_array($d['langue'] ?? '', ['fr','en']) ? $d['langue'] : 'fr',
        ]);
        // ⚠️ IMPORTANT : on retourne l'ID réellement inséré (lastInsertId),
        // pas le booléen de execute(), sinon tous les nouveaux gestionnaires
        // se retrouvent associés à l'ID 1 (true => (int)1) et héritent
        // des données du premier gestionnaire créé.
        return $ok ? (int)$this->db->lastInsertId() : false;
    }

    public function mettreAJourLangue(int $id, string $langue): bool
    {
        $langue = in_array($langue, ['fr', 'en']) ? $langue : 'fr';
        $stmt = $this->db->prepare('UPDATE gestionnaires SET langue = :langue WHERE id = :id');
        return $stmt->execute([':langue' => $langue, ':id' => $id]);
    }

    public function trouverParEmail(string $email)
    {
        $stmt = $this->db->prepare('SELECT * FROM gestionnaires WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => strtolower(trim($email))]);
        return $stmt->fetch();
    }

    public function trouverParId(int $id)
    {
        $stmt = $this->db->prepare('SELECT id, nom, telephone, email, sous_service_id, langue FROM gestionnaires WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch();
    }

    public function dernierID(): int
    {
        return (int)$this->db->lastInsertId();
    }

    /* ════════════════════════════════════════════════════════
       GESTION DU PROFIL
    ════════════════════════════════════════════════════════ */

    public function mettreAJourProfil(int $id, string $nom, string $telephone): bool
    {
        $stmt = $this->db->prepare('UPDATE gestionnaires SET nom = :nom, telephone = :telephone WHERE id = :id');
        return $stmt->execute([
            ':nom' => htmlspecialchars(trim($nom)),
            ':telephone' => trim($telephone),
            ':id' => $id
        ]);
    }

    public function mettreAJourEmail(int $id, string $email): bool
    {
        $stmt = $this->db->prepare('UPDATE gestionnaires SET email = :email WHERE id = :id');
        return $stmt->execute([
            ':email' => strtolower(trim($email)),
            ':id' => $id
        ]);
    }

    public function mettreAJourMotDePasse(int $id, string $password): bool
    {
        $stmt = $this->db->prepare('UPDATE gestionnaires SET password = :password WHERE id = :id');
        return $stmt->execute([
            ':password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]),
            ':id' => $id
        ]);
    }

    public function verifierMotDePasse(int $id, string $password): bool
    {
        $stmt = $this->db->prepare('SELECT password FROM gestionnaires WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $hash = $stmt->fetchColumn();
        return password_verify($password, $hash);
    }

    /* ════════════════════════════════════════════════════════
       SOUS-SERVICES
    ════════════════════════════════════════════════════════ */

    public function getSousServices(): array
    {
        $stmt = $this->db->query(
            'SELECT ss.id, ss.nom, s.nom AS service_nom
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE ss.statut = "actif" AND s.statut = "actif"
             ORDER BY s.nom, ss.nom'
        );
        return $stmt->fetchAll();
    }

    public function getSousServiceGestionnaire(int $gestionnaireId)
    {
        $stmt = $this->db->prepare(
            'SELECT ss.id, ss.nom, ss.duree_estimee, ss.duree_rdv_defaut,
                    ss.capacite_horaire, ss.qr_code,
                    s.nom AS service_nom, s.adresse AS service_adresse
             FROM gestionnaires g
             JOIN sous_services ss ON ss.id = g.sous_service_id
             JOIN services s ON s.id = ss.service_id
             WHERE g.id = :gid LIMIT 1'
        );
        $stmt->execute([':gid' => $gestionnaireId]);
        return $stmt->fetch();
    }

    public function getSousServiceByGestionnaire(int $gestionnaireId)
    {
        $stmt = $this->db->prepare(
            'SELECT ss.id, ss.nom, ss.service_id, s.nom as service_nom, s.adresse
             FROM gestionnaires g
             JOIN sous_services ss ON ss.id = g.sous_service_id
             JOIN services s ON s.id = ss.service_id
             WHERE g.id = :gid'
        );
        $stmt->execute([':gid' => $gestionnaireId]);
        return $stmt->fetch();
    }

    /* ════════════════════════════════════════════════════════
       DASHBOARD — STATISTIQUES
    ════════════════════════════════════════════════════════ */

    public function statsJour(int $ssId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
               COUNT(*) AS total,
               SUM(statut = "traite") AS traitees,
               SUM(statut IN ("en_attente", "confirme")) AS en_attente,
               SUM(statut = "absent") AS absentes,
               SUM(statut = "annule") AS annulees,
               SUM(mode_prise = "LIGNE") AS en_ligne,
               SUM(mode_prise = "PLACE") AS sur_place
             FROM consultations
             WHERE sous_service_id = :ss AND DATE(heure_emission) = CURDATE()'
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Évolution des consultations sur les N derniers jours pour TOUT le
     * sous-service (graphique "Évolution des consultations" du dashboard
     * gestionnaire — agrège les données de tous les médecins du service).
     */
    public function statsEvolutionSS(int $ssId, int $jours = 30): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                DATE(c.heure_passage_estimee) AS jour,
                COUNT(*) AS total,
                SUM(c.statut = 'traite') AS traitees,
                SUM(c.statut = 'absent') AS absentes,
                SUM(c.statut = 'annule') AS annulees
             FROM consultations c
             WHERE c.sous_service_id = :ss
               AND DATE(c.heure_passage_estimee) >= DATE_SUB(CURDATE(), INTERVAL :jours DAY)
               AND DATE(c.heure_passage_estimee) <= CURDATE()
             GROUP BY DATE(c.heure_passage_estimee)
             ORDER BY jour ASC"
        );
        $stmt->execute([':ss' => $ssId, ':jours' => $jours]);
        return $stmt->fetchAll();
    }

    /**
     * Totaux agrégés sur la période pour le sous-service entier (cartes
     * récapitulatives au-dessus des graphiques gestionnaire).
     */
    public function statsTotalesSS(int $ssId, int $jours = 30): array
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
             WHERE sous_service_id = :ss
               AND DATE(heure_passage_estimee) >= DATE_SUB(CURDATE(), INTERVAL :jours DAY)"
        );
        $stmt->execute([':ss' => $ssId, ':jours' => $jours]);
        return $stmt->fetch() ?: [];
    }

    /**
     * Répartition des consultations par médecin sur la période, pour le
     * diagramme en barres comparatif "Activité par médecin" du dashboard
     * gestionnaire. Inclut tous les médecins affectés au sous-service, même
     * ceux sans aucune consultation sur la période (count = 0), afin que la
     * comparaison reste juste.
     */
    public function repartitionParMedecin(int $ssId, int $jours = 30): array
    {
        $stmt = $this->db->prepare(
            "SELECT
                m.id,
                CONCAT(m.prenom, ' ', m.nom) AS medecin_nom,
                COUNT(c.id) AS total,
                SUM(c.statut = 'traite') AS traitees,
                SUM(c.statut = 'absent') AS absentes,
                SUM(c.statut = 'annule') AS annulees
             FROM medecins m
             JOIN medecin_sous_service mss ON mss.medecin_id = m.id
             LEFT JOIN consultations c
                    ON c.medecin_id = m.id
                   AND c.sous_service_id = :ss1
                   AND DATE(c.heure_passage_estimee) >= DATE_SUB(CURDATE(), INTERVAL :jours DAY)
                   AND DATE(c.heure_passage_estimee) <= CURDATE()
             WHERE mss.sous_service_id = :ss2
             GROUP BY m.id, medecin_nom
             ORDER BY total DESC"
        );
        $stmt->execute([':ss1' => $ssId, ':ss2' => $ssId, ':jours' => $jours]);
        return $stmt->fetchAll();
    }

    /**
     * Évolution du temps d'attente moyen par jour pour tout le sous-service.
     * Temps d'attente = heure_debut_reelle - heure_emission (du ticket).
     */
    public function evolutionTempsAttenteSS(int $ssId, int $jours = 30): array
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
             WHERE c.sous_service_id = :ss
               AND DATE(c.heure_emission) >= DATE_SUB(CURDATE(), INTERVAL :jours DAY)
               AND DATE(c.heure_emission) <= CURDATE()
             GROUP BY DATE(c.heure_emission)
             ORDER BY jour ASC"
        );
        $stmt->execute([':ss' => $ssId, ':jours' => $jours]);
        return $stmt->fetchAll();
    }

    /**
     * Temps d'attente moyen global depuis le début, pour tout le sous-service.
     */
    public function tempsAttenteGlobalSS(int $ssId): array
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
             WHERE c.sous_service_id = :ss
               AND c.statut = 'traite'
               AND c.heure_debut_reelle IS NOT NULL"
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetch() ?: [];
    }

    /* ════════════════════════════════════════════════════════
       DASHBOARD — FILE D'ATTENTE
    ════════════════════════════════════════════════════════ */

    /**
     * Réassigne un médecin valide aux consultations du jour qui n'en ont
     * pas (medecin_id NULL ou 0 — orphelines, invisibles côté médecin).
     * Symétrique à MedecinModel::affecterConsultationsSansMedecinDuJour() :
     * appelée ici aussi pour que la réparation s'applique dès le prochain
     * rafraîchissement du dashboard gestionnaire, sans devoir attendre que
     * le médecin concerné ouvre le sien.
     */
    public function affecterConsultationsSansMedecinDuJour(int $ssId, ?string $date = null): int
    {
        $date = $date ?: date('Y-m-d');

        $stmt = $this->db->prepare(
            'SELECT id
             FROM consultations
             WHERE sous_service_id = :ss
               AND (medecin_id IS NULL OR medecin_id = 0)
               AND DATE(COALESCE(heure_passage_estimee, heure_emission)) = :date
               AND statut IN ("en_attente","confirme","en_cours","en_pause")
             ORDER BY heure_emission ASC, id ASC'
        );
        $stmt->execute([':ss' => $ssId, ':date' => $date]);

        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!$ids) {
            return 0;
        }

        $assigned = 0;
        foreach ($ids as $consultationId) {
            $medecinId = $this->choisirMedecinMoinsOccupe($ssId);
            if (!$medecinId) {
                break;
            }

            $upd = $this->db->prepare(
                'UPDATE consultations SET medecin_id = :mid WHERE id = :id AND (medecin_id IS NULL OR medecin_id = 0)'
            );
            $upd->execute([':mid' => $medecinId, ':id' => (int)$consultationId]);

            if ($upd->rowCount() > 0) {
                $assigned++;
            }
        }

        return $assigned;
    }

    public function fileAttente(int $ssId): array
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
            'SELECT c.id, c.rang, c.statut, c.mode_prise, c.heure_passage_estimee,
                    c.heure_pause, c.motif_pause, c.motif, c.priorite_retour,
                    c.medecin_id,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.telephone,
                    CONCAT(m.prenom, " ", m.nom) AS medecin_nom
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             LEFT JOIN medecins m ON m.id = c.medecin_id
             WHERE c.sous_service_id = :ss
               AND c.statut IN ("en_attente", "confirme", "en_cours", "en_pause")
               AND DATE(COALESCE(c.heure_passage_estimee, c.heure_emission)) = CURDATE()
             ORDER BY
               CASE WHEN c.statut IN ("en_cours","en_pause") THEN 0 ELSE 1 END ASC,
               c.priorite_retour DESC,
               c.rang ASC'
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetchAll();
    }

    public function consultationsJour(int $ssId): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.rang, c.statut, c.mode_prise, c.heure_passage_estimee,
                    c.heure_debut_reelle, c.heure_fin_reelle, c.motif,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.telephone,
                    CONCAT(m.prenom, " ", m.nom) AS medecin_nom
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             LEFT JOIN medecins m ON m.id = c.medecin_id
             WHERE c.sous_service_id = :ss AND DATE(c.heure_emission) = CURDATE()
             ORDER BY c.rang'
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetchAll();
    }

    /* ════════════════════════════════════════════════════════
       ACTIONS GESTIONNAIRE
    ════════════════════════════════════════════════════════ */

    public function majStatutConsultation(int $consultationId, string $statut): bool
    {
        $stmt = $this->db->prepare('UPDATE consultations SET statut = :s WHERE id = :id');
        return $stmt->execute([':s' => $statut, ':id' => $consultationId]);
    }

    /**
     * Signale au médecin que le patient est physiquement revenu de son
     * examen ("cloche"). Ne change PAS le statut de la consultation — elle
     * reste "en_pause" — et ne fait que poser priorite_retour=1 pour que le
     * patient remonte en tête de la liste du médecin avec son bandeau
     * d'alerte habituel.
     *
     * ⚠️ Le gestionnaire ne peut PAS démarrer la consultation à la place du
     * médecin : c'est une décision médicale qui reste entièrement manuelle,
     * déclenchée par le médecin lui-même via son propre bouton "Reprendre"
     * (MedecinModel::reprendreConsultation()). Cette fonction remplace
     * l'ancienne reprendreConsultation() du gestionnaire, qui faisait passer
     * la consultation directement en "en_cours" — ce qui n'est plus permis.
     */
    public function signalerRetourPatient(int $consultationId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations
             SET priorite_retour = 1
             WHERE id = :id AND statut = "en_pause"'
        );
        return $stmt->execute([':id' => $consultationId]) && $stmt->rowCount() > 0;
    }


    /**
     * ⚠️ Aucun mécanisme automatique ne doit changer le statut d'une
     * consultation sans action humaine explicite (gestionnaire ou médecin).
     * La fonction verifierAbsencesAuto() qui marquait "absent" tout seule
     * a été retirée pour cette raison : seul un clic du gestionnaire ou du
     * médecin (ex: bouton "Absent") doit pouvoir produire ce changement.
     */

    public function appelerSuivant(int $ssId)
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.rang, p.nom, p.prenom, p.telephone
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             WHERE c.sous_service_id = :ss
               AND c.statut = "en_attente"
               AND DATE(c.heure_emission) = CURDATE()
             ORDER BY c.rang LIMIT 1'
        );
        $stmt->execute([':ss' => $ssId]);
        $suivant = $stmt->fetch();
        if ($suivant) {
            $this->majStatutConsultation($suivant['id'], 'en_cours');
        }
        return $suivant;
    }

    /* ════════════════════════════════════════════════════════
       URGENCES
    ════════════════════════════════════════════════════════ */

    public function urgencesOuvertes(int $ssId): array
    {
        $stmt = $this->db->prepare(
            'SELECT u.id, u.description, u.priorite, u.statut, u.created_at
             FROM urgences u
             JOIN sous_services ss ON ss.service_id = u.service_id
             WHERE ss.id = :ss AND u.statut != "cloturee"
             ORDER BY u.priorite ASC, u.created_at DESC'
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetchAll();
    }

    /* ════════════════════════════════════════════════════════
       CONSULTATION MANUELLE - CORRIGÉE
       Le système assigne automatiquement le médecin le moins chargé
    ════════════════════════════════════════════════════════ */

    public function patientADejaConsultationAujourdhui(int $patientId, int $ssId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM consultations 
             WHERE patient_id = :patient_id 
               AND sous_service_id = :ss_id 
               AND DATE(heure_emission) = CURDATE()
               AND statut NOT IN ("annule", "absent")'
        );
        $stmt->execute([
            ':patient_id' => $patientId,
            ':ss_id' => $ssId
        ]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function rechercherOuCreerPatient(array $d): int
    {
        $telephone = trim($d['telephone']);
        $email     = trim($d['email']);
        $nom       = htmlspecialchars(trim($d['nom']));
        $prenom    = htmlspecialchars(trim($d['prenom']));

        $stmt = $this->db->prepare('SELECT id FROM patients WHERE telephone = :tel LIMIT 1');
        $stmt->execute([':tel' => $telephone]);
        $row = $stmt->fetch();
        if ($row) return (int)$row['id'];

        if (!empty($email)) {
            $stmt = $this->db->prepare('SELECT id FROM patients WHERE email = :email LIMIT 1');
            $stmt->execute([':email' => $email]);
            $row = $stmt->fetch();
            if ($row) return (int)$row['id'];
        }

        $emailFinal = !empty($email) ? $email : $telephone . '@noemail.local';
        $stmt = $this->db->prepare(
            'INSERT INTO patients (nom, prenom, telephone, email, statut)
             VALUES (:nom, :prenom, :tel, :email, "actif")'
        );
        $stmt->execute([
            ':nom'    => $nom,
            ':prenom' => $prenom,
            ':tel'    => $telephone,
            ':email'  => $emailFinal,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function prochainRang(int $ssId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(MAX(rang), 0) + 1
             FROM consultations
             WHERE sous_service_id = :ss AND DATE(heure_emission) = CURDATE()'
        );
        $stmt->execute([':ss' => $ssId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Enregistre une consultation manuelle - CORRIGÉE
     * Le système assigne automatiquement le médecin le moins chargé
     * Pas besoin de sélectionner un médecin manuellement
     */
    /**
     * Calcule l'heure de passage estimée et l'heure de fin estimée pour une nouvelle
     * consultation sur un jour donné, en se basant sur la dernière consultation
     * planifiée ce jour-là POUR CE MÉDECIN et les horaires d'ouverture du service.
     *
     * Un sous-service peut avoir plusieurs médecins en parallèle : chacun a sa
     * propre file. Le calcul doit donc se baser uniquement sur les consultations
     * déjà attribuées à ce médecin précis, et non sur celles de tout le
     * sous-service, sinon deux médecins qui commencent tous les deux à l'heure
     * d'ouverture se retrouveraient avec des heures estimées qui s'enchaînent
     * comme s'ils étaient un seul médecin.
     *
     * Règles (par médecin) :
     *  - 1er patient du médecin ce jour  → debut = heure d'ouverture, fin = debut + duree_estimee
     *  - Patient suivant du même médecin → debut = fin du précédent (du même médecin), fin = debut + duree_estimee
     *
     * @return array{heure_debut: string, heure_fin: string}  (format 'Y-m-d H:i:s')
     */
    public function calculerHeurePassageEstimee(int $ssId, string $date, int $medecinId = 0): array
    {
        $dureeEstimee = $this->getDureeEstimee($ssId); // en secondes

        // Horaires d'ouverture du service (communs à tous les médecins du sous-service)
        $horaires = $this->getServiceHoraires($ssId);
        $heureOuverture = $horaires['horaires_ouverture'] ?? '08:00:00';

        // Trouver la fin estimée de la dernière consultation planifiée ce jour
        // POUR CE MÉDECIN UNIQUEMENT, afin que chaque médecin ait sa propre file.
        $conditionsMedecin = $medecinId > 0 ? ' AND medecin_id = :medecin_id' : ' AND medecin_id IS NULL';
        $stmt = $this->db->prepare(
            'SELECT heure_passage_estimee, heure_debut_reelle, duree_estimee
             FROM consultations
             WHERE sous_service_id = :ss
               AND DATE(heure_passage_estimee) = :date
               AND statut NOT IN ("annule","absent")'
            . $conditionsMedecin .
            ' ORDER BY heure_passage_estimee DESC
             LIMIT 1'
        );
        $params = [':ss' => $ssId, ':date' => $date];
        if ($medecinId > 0) {
            $params[':medecin_id'] = $medecinId;
        }
        $stmt->execute($params);
        $last = $stmt->fetch();

        if ($last && $last['heure_passage_estimee']) {
            // Durée moyenne de la consultation précédente (base du calcul)
            $dureePrec = (int)($last['duree_estimee'] ?: $dureeEstimee);

            // Si la consultation précédente a déjà démarré (heure_debut_reelle
            // renseignée), on se base sur son heure de DÉBUT RÉELLE + durée
            // moyenne, plus fiable que l'heure estimée car elle reflète le
            // retard ou l'avance effectif du médecin. Sinon, on garde l'heure
            // de début ESTIMÉE comme base de calcul.
            $heureBase = $last['heure_debut_reelle'] ?: $last['heure_passage_estimee'];
            $heureDebutTs = strtotime($heureBase) + $dureePrec;
        } else {
            // Premier patient : debut = heure d'ouverture, sauf si elle est déjà
            // passée (cas où on enregistre une consultation en cours de journée) :
            // dans ce cas on part de l'heure actuelle pour ne pas placer le patient
            // dans le passé.
            $heureOuvertureTs = strtotime($date . ' ' . $heureOuverture);
            $maintenantTs = time();
            $heureDebutTs = ($date === date('Y-m-d') && $heureOuvertureTs < $maintenantTs)
                ? $maintenantTs
                : $heureOuvertureTs;
        }

        $heureFinTs = $heureDebutTs + $dureeEstimee;

        return [
            'heure_debut' => date('Y-m-d H:i:s', $heureDebutTs),
            'heure_fin'   => date('Y-m-d H:i:s', $heureFinTs),
        ];
    }

    public function enregistrerConsultationManuelle(array $d): int
    {
        $ssId      = (int)$d['sous_service_id'];
        $patientId = (int)$d['patient_id'];
        $date      = $d['date'] ?? date('Y-m-d'); // aujourd'hui par défaut

        $dejaEnTransaction = $this->db->inTransaction();
        if (!$dejaEnTransaction) {
            $this->db->beginTransaction();
        }

        try {
            // Verrou de ligne : empêche deux requêtes simultanées de passer
            // toutes les deux la vérification avant qu'aucune n'ait inséré.
            $stmtLock = $this->db->prepare(
                'SELECT id FROM consultations
                 WHERE patient_id = :patient_id
                   AND sous_service_id = :ss_id
                   AND DATE(heure_emission) = CURDATE()
                   AND statut NOT IN ("annule", "absent")
                 FOR UPDATE'
            );
            $stmtLock->execute([':patient_id' => $patientId, ':ss_id' => $ssId]);
            if ($stmtLock->fetch()) {
                throw new Exception('Ce patient a déjà une consultation active aujourd\'hui dans cette file d\'attente.');
            }

            $modesAutorises = ['LIGNE', 'PLACE', 'MANUEL', 'QR_CODE'];
            $modePriseDB = (isset($d['mode_prise']) && in_array($d['mode_prise'], $modesAutorises, true))
                ? $d['mode_prise']
                : 'PLACE';
            $statut = 'en_attente';
            if (isset($d['statut']) && in_array($d['statut'], ['en_attente', 'confirme'])) {
                $statut = $d['statut'];
            }

            // Assignation automatique du médecin le moins chargé.
            // ⚠️ Si aucun médecin "disponible" n'est affecté à ce sous-service,
            // choisirMedecinMoinsOccupe() retourne 0 (pas NULL). On DOIT bloquer
            // ici : une consultation insérée avec medecin_id = 0 serait invisible
            // côté médecin (qui filtre sur un medecin_id réel) ET ne serait
            // jamais réparée par affecterConsultationsSansMedecinDuJour(), qui ne
            // recherche que medecin_id IS NULL (0 n'est pas NULL en SQL).
            $medecinId = $this->choisirMedecinMoinsOccupe($ssId);
            if ($medecinId <= 0) {
                throw new Exception('Aucun médecin disponible n\'est affecté à ce sous-service pour le moment. Impossible d\'enregistrer la consultation.');
            }

            $rang = $this->prochainRang($ssId);
            $dureeEstimee = $this->getDureeEstimee($ssId);
            $motif = !empty($d['motif']) ? htmlspecialchars(trim($d['motif'])) : null;

            // Calcul des heures basé sur la logique séquentielle PROPRE À CE MÉDECIN
            $heures = $this->calculerHeurePassageEstimee($ssId, $date, $medecinId);
            $heurePassage = $heures['heure_debut'];

            $sql = "INSERT INTO consultations 
                    (patient_id, sous_service_id, medecin_id, qr_code_id, statut, rang, mode_prise, 
                     heure_emission, heure_passage_estimee, duree_estimee, motif)
                    VALUES 
                    (:patient_id, :sous_service_id, :medecin_id, :qr_code_id, :statut, :rang, :mode_prise,
                     NOW(), :heure_passage_estimee, :duree_estimee, :motif)";

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':patient_id'           => $patientId,
                ':sous_service_id'      => $ssId,
                ':medecin_id'           => $medecinId,
                ':qr_code_id'           => $d['qr_code_id'] ?? null,
                ':statut'               => $statut,
                ':rang'                 => $rang,
                ':mode_prise'           => $modePriseDB,
                ':heure_passage_estimee'=> $heurePassage,
                ':duree_estimee'        => $dureeEstimee,
                ':motif'                => $motif
            ]);

            if (!$result) {
                if (!$dejaEnTransaction) $this->db->rollBack();
                return 0;
            }

            $nouvelId = (int)$this->db->lastInsertId();
            if (!$dejaEnTransaction) $this->db->commit();
            return $nouvelId;
        } catch (\Throwable $e) {
            if (!$dejaEnTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Sélectionne le médecin le moins occupé du sous-service
     * Compte les consultations en cours et en attente
     */
    public function choisirMedecinMoinsOccupe(int $ssId): int
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
                   AND DATE(heure_emission) = CURDATE()
                   AND statut IN ("en_attente","confirme","en_cours")
                 GROUP BY medecin_id
             ) occ ON occ.medecin_id = m.id
             WHERE mss.sous_service_id = :ss2 AND m.statut = "disponible"
             ORDER BY COALESCE(occ.nb, 0) ASC, m.id ASC
             LIMIT 1'
        );
        $stmt->execute([':ss1' => $ssId, ':ss2' => $ssId]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : 0;
    }

    public function getDureeEstimee(int $ssId): int
    {
        $stmt = $this->db->prepare('SELECT COALESCE(duree_estimee, duree_rdv_defaut, 1800) FROM sous_services WHERE id = :id');
        $stmt->execute([':id' => $ssId]);
        return (int)($stmt->fetchColumn() ?: 1800);
    }

    public function getMedecinsDisponibles(int $ssId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.id, m.nom, m.prenom, m.specialite, m.statut,
                    COALESCE(occ.nb, 0) AS nb_en_attente
             FROM medecins m
             JOIN medecin_sous_service mss ON mss.medecin_id = m.id
             LEFT JOIN (
                 SELECT medecin_id, COUNT(*) AS nb
                 FROM consultations
                 WHERE sous_service_id = :ss2
                   AND DATE(heure_emission) = CURDATE()
                   AND statut IN ("en_attente","confirme","en_cours")
                 GROUP BY medecin_id
             ) occ ON occ.medecin_id = m.id
             WHERE mss.sous_service_id = :ss AND m.statut = "disponible"
             ORDER BY occ.nb ASC, m.nom'
        );
        $stmt->execute([':ss' => $ssId, ':ss2' => $ssId]);
        return $stmt->fetchAll();
    }

    public function getTousMedecins(int $ssId): array
    {
        $stmt = $this->db->prepare(
            'SELECT m.id, m.nom, m.prenom, m.specialite
             FROM medecins m
             JOIN medecin_sous_service mss ON mss.medecin_id = m.id
             WHERE mss.sous_service_id = :ss
             ORDER BY m.nom'
        );
        $stmt->execute([':ss' => $ssId]);
        return $stmt->fetchAll();
    }

    public function chercherPatientParTel(string $telephone)
    {
        $stmt = $this->db->prepare('SELECT id, nom, prenom, telephone, email FROM patients WHERE telephone = :tel LIMIT 1');
        $stmt->execute([':tel' => $telephone]);
        return $stmt->fetch();
    }

    /* ════════════════════════════════════════════════════════
       HORAIRES ET PLANNING
    ════════════════════════════════════════════════════════ */

    public function getHorairesJour(int $ssId, ?string $date = null): array
    {
        if (!$date) $date = date('Y-m-d');
        
        $stmt = $this->db->prepare(
            'SELECT edt.*, m.nom as medecin_nom, m.prenom as medecin_prenom
             FROM emplois_du_temps edt
             LEFT JOIN medecins m ON m.id = edt.medecin_id
             WHERE edt.sous_service_id = :ss AND edt.jour = :date
             ORDER BY edt.heure_debut'
        );
        $stmt->execute([':ss' => $ssId, ':date' => $date]);
        return $stmt->fetchAll();
    }

    /**
     * Consultations d'un sous-service sur une plage de dates (pour le planning semaine)
     */
    public function consultationsParPeriode(int $ssId, string $dateDebut, string $dateFin): array
    {
        $stmt = $this->db->prepare(
            'SELECT c.id, c.rang, c.statut, c.mode_prise, c.duree_estimee,
                    c.heure_passage_estimee, c.heure_debut_reelle, c.heure_fin_reelle, c.motif,
                    c.medecin_id,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.telephone,
                    CONCAT(m.prenom, " ", m.nom) AS medecin_nom
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             LEFT JOIN medecins m ON m.id = c.medecin_id
             WHERE c.sous_service_id = :ss
               AND DATE(c.heure_passage_estimee) BETWEEN :debut AND :fin
             ORDER BY c.heure_passage_estimee'
        );
        $stmt->execute([':ss' => $ssId, ':debut' => $dateDebut, ':fin' => $dateFin]);
        return $stmt->fetchAll();
    }

    /**
     * Jours de travail d'un médecin (numéros 1=lundi … 7=dimanche)
     */
    public function getJoursTravailMedecin(int $medecinId): array
    {
        $stmt = $this->db->prepare(
            'SELECT jour_semaine FROM medecin_jours_travail WHERE medecin_id = :mid AND actif = 1'
        );
        $stmt->execute([':mid' => $medecinId]);
        return array_column($stmt->fetchAll(), 'jour_semaine');
    }

    /**
     * Horaires du service associé à un sous-service
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

    /**
     * Historique paginé des consultations du sous-service (toutes dates passées)
     */
    public function historiquePagine(int $ssId, int $page, int $perPage, string $statut = '', string $dateDebut = '', string $dateFin = ''): array
    {
        $conditions = ['c.sous_service_id = :ss', '(DATE(c.heure_passage_estimee) < CURDATE() OR c.statut IN ("traite","annule","absent"))'];
        $params = [':ss' => $ssId];

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

        $stmtCount = $this->db->prepare("SELECT COUNT(*) FROM consultations c WHERE $where");
        $stmtCount->execute($params);
        $total = (int)$stmtCount->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $stmt = $this->db->prepare(
            "SELECT c.id, c.rang, c.statut, c.mode_prise, c.motif,
                    c.heure_passage_estimee, c.heure_debut_reelle, c.heure_fin_reelle,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.telephone,
                    CONCAT(m.prenom, ' ', m.nom) AS medecin_nom
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             LEFT JOIN medecins m ON m.id = c.medecin_id
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
            'data'      => $stmt->fetchAll(),
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    /**
     * Nombre de consultations de l'historique groupées par statut (pour les pastilles
     * de filtre "Tous / En cours / Traitée / …"). Tient compte des filtres de date
     * mais jamais du filtre de statut, afin que les compteurs restent stables
     * quel que soit le filtre actuellement sélectionné.
     */
    public function historiqueCounts(int $ssId, string $dateDebut = '', string $dateFin = ''): array
    {
        $conditions = ['c.sous_service_id = :ss', '(DATE(c.heure_passage_estimee) < CURDATE() OR c.statut IN ("traite","annule","absent"))'];
        $params = [':ss' => $ssId];

        if ($dateDebut !== '') {
            $conditions[] = 'DATE(c.heure_passage_estimee) >= :ddebut';
            $params[':ddebut'] = $dateDebut;
        }
        if ($dateFin !== '') {
            $conditions[] = 'DATE(c.heure_passage_estimee) <= :dfin';
            $params[':dfin'] = $dateFin;
        }

        $where = implode(' AND ', $conditions);

        $stmt = $this->db->prepare("SELECT c.statut, COUNT(*) AS nb FROM consultations c WHERE $where GROUP BY c.statut");
        $stmt->execute($params);

        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['statut']] = (int)$row['nb'];
        }
        return $counts;
    }
}