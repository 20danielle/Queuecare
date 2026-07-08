<?php
/**
 * models/MedecinModel.php
 * Modèle MVC — Médecin (Version mono-service)
 */

require_once __DIR__ . '/../config/database.php';

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
            'INSERT INTO medecins (nom, prenom, specialite, telephone, email, password, statut, photo, langue)
             VALUES (:nom, :prenom, :specialite, :telephone, :email, :password, "disponible", :photo, :langue)'
        );
        return $stmt->execute([
            ':nom'        => htmlspecialchars(trim($d['nom'])),
            ':prenom'     => htmlspecialchars(trim($d['prenom'])),
            ':specialite' => htmlspecialchars(trim($d['specialite'])),
            ':telephone'  => trim($d['telephone']),
            ':email'      => strtolower(trim($d['email'])),
            ':password'   => $d['password'],  // hash bcrypt fourni par le controller
            ':photo'      => $d['photo'] ?? null,
            ':langue'     => in_array($d['langue'] ?? '', ['fr','en']) ? $d['langue'] : 'fr',
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

    /**
     * À appeler à chaque accès au dashboard médecin (chargement de page ET
     * heartbeat AJAX). Sert de marqueur "le médecin est bien connecté
     * aujourd'hui" pour le routage automatique des consultations.
     */
    public function marquerConnexionDashboard(int $medecinId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE medecins SET derniere_connexion_dashboard = NOW() WHERE id = :id'
        );
        return $stmt->execute([':id' => $medecinId]);
    }

    /**
     * Un médecin est considéré "connecté aujourd'hui" s'il a chargé son
     * dashboard au moins une fois depuis minuit. Cela empêche
     * choisirMedecinMoinsOccupe() de lui envoyer des patients tant qu'il
     * n'a pas explicitement ouvert son espace (retard ou indisponibilité
     * non prévenue).
     */
    public function estConnecteAujourdhui(int $medecinId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT derniere_connexion_dashboard FROM medecins
             WHERE id = :id AND DATE(derniere_connexion_dashboard) = CURDATE()'
        );
        $stmt->execute([':id' => $medecinId]);
        return (bool)$stmt->fetchColumn();
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
               AND DATE(m.derniere_connexion_dashboard) = CURDATE()
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

        // ⚠️ On répare ici aussi bien medecin_id IS NULL que medecin_id = 0 :
        // une consultation orpheline a pu être insérée avec 0 (valeur de
        // repli de choisirMedecinMoinsOccupe() avant le correctif qui bloque
        // désormais sa création), et resterait sinon invisible pour toujours
        // côté médecin sans jamais être reprise par cette réparation.
        $stmt = $this->db->prepare(
            'SELECT id
             FROM consultations
             WHERE sous_service_id = :ss
               AND (medecin_id IS NULL OR medecin_id = 0)
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
                'UPDATE consultations SET medecin_id = :mid WHERE id = :id AND (medecin_id IS NULL OR medecin_id = 0)'
            );
            $upd->execute([
                ':mid' => $medecinId,
                ':id' => (int)$consultationId,
            ]);

            if ($upd->rowCount() > 0) {
                $assigned++;

                // Occuper le créneau correspondant dans le planning du
                // médecin nouvellement affecté (la consultation n'ayant pas
                // pu réserver de créneau au moment de sa création puisqu'aucun
                // médecin n'était encore disponible).
                $stmtHeure = $this->db->prepare(
                    'SELECT heure_passage_estimee FROM consultations WHERE id = :id'
                );
                $stmtHeure->execute([':id' => (int)$consultationId]);
                $heurePassage = $stmtHeure->fetchColumn();
                if ($heurePassage) {
                    $this->reserverCreneauEdt($ssId, $medecinId, $date, $heurePassage);
                }
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

    /**
     * Statistiques du jour pour le dashboard médecin.
     *
     * Si $medecinId est fourni, les statistiques sont strictement limitées
     * aux consultations de ce médecin (cartes "Mes statistiques" du dashboard
     * médecin). Si $medecinId est null, les statistiques portent sur tout le
     * sous-service (comportement historique, conservé pour compatibilité avec
     * d'éventuels appels qui souhaiteraient encore une vue globale).
     */
    public function statsJour(int $ssId, ?int $medecinId = null): array
    {
        $conditions = ['sous_service_id = :ss', 'DATE(heure_emission) = CURDATE()'];
        $params     = [':ss' => $ssId];

        if ($medecinId !== null) {
            $conditions[] = 'medecin_id = :mid';
            $params[':mid'] = $medecinId;
        }

        $where = implode(' AND ', $conditions);

        $stmt = $this->db->prepare(
            "SELECT
               COUNT(*) AS total,
               SUM(statut = 'traite') AS traitees,
               SUM(statut IN ('en_attente','confirme')) AS en_attente,
               SUM(statut = 'absent') AS absentes,
               SUM(statut = 'annule') AS annulees,
               COALESCE(ROUND(AVG(
                 CASE WHEN heure_debut_reelle IS NOT NULL
                       AND heure_fin_reelle IS NOT NULL
                      THEN TIMESTAMPDIFF(SECOND, heure_debut_reelle, heure_fin_reelle)
                 END
               )), 0) AS duree_moy_sec
             FROM consultations
             WHERE $where"
        );
        $stmt->execute($params);
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
            'SELECT c.id, c.rang, c.statut, c.mode_prise,
                    c.heure_passage_estimee, c.heure_debut_reelle, c.heure_fin_reelle, c.motif,
                    c.heure_pause, c.motif_pause, c.priorite_retour,
                    CASE WHEN c.statut = "en_pause" AND c.heure_pause IS NOT NULL
                         THEN TIMESTAMPDIFF(SECOND, c.heure_pause, NOW())
                         ELSE 0 END AS secondes_en_pause,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.telephone
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             WHERE c.sous_service_id = :ss
               AND c.medecin_id = :mid
               AND DATE(c.heure_passage_estimee) = CURDATE()
               AND c.statut != "annule"
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
            'SELECT c.id, c.rang, c.statut, c.mode_prise, c.duree_estimee,
                    c.heure_passage_estimee, c.heure_debut_reelle, c.heure_fin_reelle, c.motif,
                    p.nom AS patient_nom, p.prenom AS patient_prenom, p.telephone
             FROM consultations c
             JOIN patients p ON p.id = c.patient_id
             WHERE c.sous_service_id = :ss
               AND c.medecin_id = :mid
               AND DATE(c.heure_passage_estimee) BETWEEN :date_debut AND :date_fin
               AND c.statut != "annule"
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
     *
     * ⚠️ Ne met PAS priorite_retour=1 ici : ce flag ne doit refléter que le
     * signal explicite envoyé par le gestionnaire quand le patient revient
     * physiquement des examens (cf. GestionnaireModel::signalerRetourPatient()),
     * pas le simple fait que le patient soit parti. Tant que le gestionnaire
     * n'a pas "sonné la cloche", le médecin ne doit voir aucune alerte de
     * retour prioritaire pour cette consultation.
     */
    public function mettreEnPause(int $consultationId, string $motif = ''): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations
             SET statut         = "en_pause",
                 heure_pause    = NOW(),
                 motif_pause    = :motif,
                 priorite_retour = 0
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
     * ⚠️ Aucun mécanisme automatique ne doit changer le statut d'une
     * consultation sans action humaine explicite. La fonction
     * verifierAbsencesAuto() qui marquait "absent" tout seule (y compris
     * après une pause prolongée) a été retirée pour cette raison : seul un
     * clic du médecin (bouton "Absent") doit pouvoir produire ce changement.
     */

    public function annulerConsultation(int $consultationId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE consultations 
             SET statut = "annule" 
             WHERE id = :id AND statut NOT IN ("traite")'
        );
        return $stmt->execute([':id' => $consultationId]);
    }

    /* ════════════════════════════════════════════════════════
       BOUTON "URGENCE" (statut disponible/indisponible du médecin)
       ────────────────────────────────────────────────────────
       Cas d'usage : le gestionnaire signale, depuis SON dashboard, qu'un
       médecin de son sous-service est appelé en urgence et doit quitter.
       Contrairement au report au lendemain (annulerToutesConsultations),
       sa file du jour n'est PAS perdue : les consultations pas encore
       prises en charge sont réparties entre les autres médecins
       disponibles du sous-service (reaffecterFileVersMedecinsDisponibles),
       et le médecin les récupère automatiquement (restaurerFileMedecin)
       dès sa prochaine connexion si elles n'ont pas encore été traitées.
    ════════════════════════════════════════════════════════ */

    /**
     * Un médecin est considéré "en pleine consultation" uniquement s'il a
     * une consultation en_cours. Une consultation en_pause (examen
     * externe) ne compte PAS comme active : dès qu'il la met en pause (ou
     * la termine), une urgence en attente doit pouvoir s'appliquer
     * immédiatement (cf. appliquerUrgenceSiEnAttente()).
     */
    public function aUneConsultationActive(int $medecinId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM consultations
             WHERE medecin_id = :mid AND statut = "en_cours"'
        );
        $stmt->execute([':mid' => $medecinId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Déclenche le mode "urgence" pour un médecin, suite au clic du
     * gestionnaire sur le bouton dédié de SON dashboard (le gestionnaire
     * signale qu'un médecin de son sous-service est appelé en urgence).
     * - Si le médecin n'a aucune consultation en_cours (donc aussi s'il
     *   n'a simplement aucune consultation, ou si elle est déjà en_pause) :
     *   bascule immédiate en "indisponible" (retour ['immediate' => true])
     *   + on mémorise qu'une notification doit encore être délivrée à son
     *   dashboard (potentiellement ouvert dans un autre onglet/session),
     *   qui devra afficher une alerte puis se déconnecter automatiquement.
     * - Sinon (consultation en_cours) : on mémorise juste la demande
     *   (urgence_en_attente = 1) ; la bascule effective aura lieu via
     *   appliquerUrgenceSiEnAttente() dès que le médecin termine cette
     *   consultation OU la met simplement en pause (examen externe) — le
     *   message + la déconnexion sont alors renvoyés directement dans la
     *   réponse de l'action correspondante (terminerConsultationAjax,
     *   marquerAbsentAjax ou mettreEnPauseAjax).
     *
     * @return array{immediate: bool}
     */
    public function declencherUrgenceParGestionnaire(int $medecinId, int $gestionnaireId): array
    {
        if ($this->aUneConsultationActive($medecinId)) {
            $stmt = $this->db->prepare(
                'UPDATE medecins
                 SET urgence_en_attente = 1,
                     urgence_declenchee_par_gestionnaire_id = :gid
                 WHERE id = :id'
            );
            $stmt->execute([':gid' => $gestionnaireId, ':id' => $medecinId]);
            return ['immediate' => false];
        }

        $stmt = $this->db->prepare(
            'UPDATE medecins
             SET statut = "indisponible",
                 urgence_en_attente = 0,
                 urgence_notification_en_attente = 1,
                 urgence_declenchee_par_gestionnaire_id = :gid
             WHERE id = :id'
        );
        $stmt->execute([':gid' => $gestionnaireId, ':id' => $medecinId]);
        return ['immediate' => true];
    }

    /**
     * À appeler juste après qu'une consultation en_cours soit terminée,
     * que le patient ait été marqué absent, OU que la consultation soit
     * simplement mise en pause (examen externe) — les trois événements qui
     * font sortir le médecin de l'état "en pleine consultation". Si une
     * urgence était en attente pour ce médecin ET qu'il n'a plus aucune
     * consultation en_cours, on bascule enfin son statut à "indisponible".
     *
     * @return bool true si l'urgence a bien été appliquée — l'appelant
     *              doit alors déconnecter le médecin — false sinon.
     */
    public function appliquerUrgenceSiEnAttente(int $medecinId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT urgence_en_attente FROM medecins WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $medecinId]);

        if (!(bool)$stmt->fetchColumn()) {
            return false;
        }

        // Une autre consultation est peut-être passée en_cours entre temps
        // (ex: reprise d'une pause précédente) : on attend qu'elle se
        // termine (ou soit mise en pause) elle aussi.
        if ($this->aUneConsultationActive($medecinId)) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE medecins SET statut = "indisponible", urgence_en_attente = 0 WHERE id = :id'
        );
        $stmt->execute([':id' => $medecinId]);
        return true;
    }

    /**
     * À appeler à chaque connexion réussie d'un médecin au dashboard.
     * Le simple fait de se reconnecter signifie qu'il est de nouveau
     * opérationnel : on annule toute urgence en attente et on repasse
     * son statut à "disponible" (actif).
     */
    public function reactiverApresConnexion(int $medecinId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE medecins
             SET statut = "disponible",
                 urgence_en_attente = 0,
                 urgence_notification_en_attente = 0,
                 urgence_declenchee_par_gestionnaire_id = NULL
             WHERE id = :id'
        );
        return $stmt->execute([':id' => $medecinId]);
    }

    /**
     * Consomme (lit puis efface) le drapeau "notification d'urgence en
     * attente" d'un médecin. Utilisé par le polling du dashboard médecin
     * (heartbeat AJAX) pour détecter qu'une urgence a été déclenchée par le
     * gestionnaire pendant qu'aucune consultation n'était en cours : la
     * bascule "indisponible" a déjà eu lieu côté serveur, il ne reste plus
     * qu'à avertir le navigateur du médecin (potentiellement resté ouvert
     * sur un autre onglet) puis à le déconnecter.
     *
     * @return bool true si une notification était bien en attente.
     */
    public function consommerNotificationUrgence(int $medecinId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT urgence_notification_en_attente FROM medecins WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $medecinId]);

        if (!(bool)$stmt->fetchColumn()) {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE medecins SET urgence_notification_en_attente = 0 WHERE id = :id'
        );
        $stmt->execute([':id' => $medecinId]);
        return true;
    }

    /**
     * À appeler dès qu'un médecin se reconnecte à son dashboard (login).
     * Restitue au médecin les consultations du jour qui avaient été
     * réaffectées automatiquement à d'autres médecins pendant son
     * indisponibilité (urgence), et qui n'ont pas encore été traitées
     * (toujours "en_attente"/"confirme"). Les consultations déjà prises en
     * charge par le médecin remplaçant (en_cours/en_pause/traite/absent) ne
     * sont pas touchées.
     *
     * @return int[] Liste des IDs de consultations restituées (permet au
     *               contrôleur de notifier chaque patient concerné qu'il
     *               doit revenir vers son médecin initial).
     */
    public function restaurerFileMedecin(int $medecinId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, duree_estimee
             FROM consultations
             WHERE medecin_id_origine = :mid
               AND medecin_id != :mid2
               AND statut IN ("en_attente", "confirme")
               AND DATE(COALESCE(heure_passage_estimee, heure_emission)) = CURDATE()
             ORDER BY rang ASC, heure_emission ASC'
        );
        $stmt->execute([':mid' => $medecinId, ':mid2' => $medecinId]);
        $consultations = $stmt->fetchAll();

        if (empty($consultations)) {
            return [];
        }

        $stmtRang = $this->db->prepare(
            'SELECT COALESCE(MAX(rang), 0) FROM consultations
             WHERE medecin_id = :mid
               AND DATE(COALESCE(heure_passage_estimee, heure_emission)) = CURDATE()
               AND statut NOT IN ("annule", "absent")'
        );
        $stmtRang->execute([':mid' => $medecinId]);
        $rangBase = (int)$stmtRang->fetchColumn();

        $stmtUpdate = $this->db->prepare(
            'UPDATE consultations
             SET medecin_id = :mid,
                 medecin_id_origine = NULL,
                 rang = :rang,
                 heure_passage_estimee = :heure
             WHERE id = :id'
        );

        $aujourdhui = date('Y-m-d') . ' 00:00:00';
        $restituees = [];

        foreach ($consultations as $i => $c) {
            $rang  = $rangBase + $i + 1;
            $duree = (int)($c['duree_estimee'] ?? 1800);
            $heure = date('Y-m-d H:i:s', strtotime($aujourdhui) + ($rang - 1) * $duree);

            $stmtUpdate->execute([
                ':mid'   => $medecinId,
                ':rang'  => $rang,
                ':heure' => $heure,
                ':id'    => $c['id'],
            ]);
            $restituees[] = (int)$c['id'];
        }

        return $restituees;
    }

    /**
     * Annule les consultations du jour et les reporte au lendemain,
     * en priorité (rangs 1, 2, 3…) et dans le même ordre qu'aujourd'hui.
     */
    public function annulerToutesConsultations(int $medecinId): bool
    {
        try {
            $this->db->beginTransaction();

            // 1. Récupérer les consultations actives du jour, dans l'ordre
            $stmt = $this->db->prepare(
                'SELECT id, rang, heure_passage_estimee, duree_estimee
                 FROM consultations
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
            }

            $this->db->commit();
            return true;

        } catch (\PDOException $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Liste les médecins censés travailler aujourd'hui (jour actif +
     * affectés à un sous-service, statut "disponible") mais qui ne se sont
     * pas encore connectés à leur dashboard, alors que l'ouverture du
     * service + un délai de grâce sont déjà passés. Sert de base à la
     * détection automatique de retard/indisponibilité imprévue.
     */
    public function listerMedecinsEnRetardNonConnectes(int $delaiGraceMinutes = 20): array
    {
        $jourSemaine = (int)date('N'); // 1=Lundi..7=Dimanche

        $stmt = $this->db->prepare(
            'SELECT m.id, m.nom, m.prenom, m.statut,
                    m.derniere_connexion_dashboard,
                    mss.sous_service_id, ss.nom AS sous_service_nom,
                    s.horaires_ouverture
             FROM medecins m
             JOIN medecin_jours_travail mjt ON mjt.medecin_id = m.id AND mjt.jour_semaine = :jour AND mjt.actif = 1
             JOIN medecin_sous_service mss ON mss.medecin_id = m.id
             JOIN sous_services ss ON ss.id = mss.sous_service_id
             JOIN services s ON s.id = ss.service_id
             WHERE m.statut = "disponible"
               AND DATE(COALESCE(m.derniere_connexion_dashboard, "1970-01-01")) <> CURDATE()
               AND NOW() > TIMESTAMP(CURDATE(), s.horaires_ouverture) + INTERVAL :grace MINUTE'
        );
        $stmt->execute([':jour' => $jourSemaine, ':grace' => $delaiGraceMinutes]);
        return $stmt->fetchAll();
    }

    /**
     * Réaffecte les patients déjà en attente d'un médecin en retard/indisponible
     * vers d'autres médecins disponibles ET connectés du même sous-service.
     * Ne touche pas aux consultations déjà "en_cours"/"en_pause" (le patient
     * est déjà pris en charge physiquement).
     *
     * Retourne un détail exploitable pour la notification et l'IHM du
     * gestionnaire : ['reaffectees' => [...], 'sans_solution' => [...]]
     */
    public function reaffecterFileVersMedecinsDisponibles(int $medecinIndisponibleId, int $ssId, string $date = ''): array
    {
        $date = $date ?: date('Y-m-d');
        $reaffectees   = [];
        $sansSolution  = [];

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                'SELECT id, patient_id, duree_estimee
                 FROM consultations
                 WHERE medecin_id = :mid
                   AND sous_service_id = :ss
                   AND DATE(COALESCE(heure_passage_estimee, heure_emission)) = :date
                   AND statut IN ("en_attente", "confirme")
                 ORDER BY rang ASC, heure_emission ASC'
            );
            $stmt->execute([':mid' => $medecinIndisponibleId, ':ss' => $ssId, ':date' => $date]);
            $consultations = $stmt->fetchAll();

            foreach ($consultations as $c) {
                $nouveauMedecinId = $this->choisirMedecinMoinsOccupe($ssId, $date);

                // Aucun autre médecin dispo/connecté : on laisse le patient
                // en file (statut inchangé), à traiter manuellement.
                if (!$nouveauMedecinId || $nouveauMedecinId === $medecinIndisponibleId) {
                    $sansSolution[] = $c['id'];
                    continue;
                }

                // Nouveau rang = dernier rang du médecin cible + 1
                $stmtRang = $this->db->prepare(
                    'SELECT COALESCE(MAX(rang), 0) FROM consultations
                     WHERE medecin_id = :mid
                       AND DATE(COALESCE(heure_passage_estimee, heure_emission)) = :date
                       AND statut NOT IN ("annule", "absent")'
                );
                $stmtRang->execute([':mid' => $nouveauMedecinId, ':date' => $date]);
                $nouveauRang = (int)$stmtRang->fetchColumn() + 1;

                $duree = (int)($c['duree_estimee'] ?? 1800);
                $nouvelleHeure = date('Y-m-d H:i:s', strtotime("$date 00:00:00") + ($nouveauRang - 1) * $duree);

                // medecin_id_origine n'est renseigné que s'il ne l'était pas
                // déjà : en cas de réaffectations en cascade, on veut
                // toujours pouvoir restituer la file au tout premier
                // médecin (celui qui était réellement prévu ce jour-là),
                // pas au médecin intermédiaire.
                $stmtUpdate = $this->db->prepare(
                    'UPDATE consultations
                     SET medecin_id = :new_mid,
                         medecin_id_origine = COALESCE(medecin_id_origine, :orig_mid),
                         rang = :rang,
                         heure_passage_estimee = :heure
                     WHERE id = :id'
                );
                $stmtUpdate->execute([
                    ':new_mid'  => $nouveauMedecinId,
                    ':orig_mid' => $medecinIndisponibleId,
                    ':rang'     => $nouveauRang,
                    ':heure'    => $nouvelleHeure,
                    ':id'       => $c['id'],
                ]);

                $reaffectees[] = [
                    'consultation_id'   => (int)$c['id'],
                    'patient_id'        => (int)$c['patient_id'],
                    'ancien_medecin_id' => $medecinIndisponibleId,
                    'nouveau_medecin_id'=> $nouveauMedecinId,
                    'nouveau_rang'      => $nouveauRang,
                    'nouvelle_heure'    => $nouvelleHeure,
                ];
            }

            $this->db->commit();
        } catch (\PDOException $e) {
            $this->db->rollBack();
        }

        return ['reaffectees' => $reaffectees, 'sans_solution' => $sansSolution];
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

    public function mettreAJourLangue(int $id, string $langue): bool
    {
        $langue = in_array($langue, ['fr', 'en']) ? $langue : 'fr';
        $stmt = $this->db->prepare('UPDATE medecins SET langue = :langue WHERE id = :id');
        return $stmt->execute([':langue' => $langue, ':id' => $id]);
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
        $conditions = ['c.medecin_id = :mid', '(DATE(c.heure_passage_estimee) < CURDATE() OR c.statut IN ("traite","annule","absent"))'];
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

    /**
     * Nombre de consultations de l'historique du médecin, groupées par statut
     * (pour les pastilles de filtre "Tous / Traitée / Absent / …" affichées
     * au-dessus du tableau d'historique). Tient compte des filtres de date
     * mais jamais du filtre de statut, afin que les compteurs restent stables
     * quel que soit le filtre actuellement sélectionné.
     */
    public function historiqueCounts(int $medecinId, string $dateDebut = '', string $dateFin = ''): array
    {
        $conditions = ['c.medecin_id = :mid', '(DATE(c.heure_passage_estimee) < CURDATE() OR c.statut IN ("traite","annule","absent"))'];
        $params = [':mid' => $medecinId];

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
        // Priorité 1 : durée réelle moyenne des consultations terminées du sous-service (90 derniers jours)
        $stmt = $this->db->prepare(
            "SELECT ROUND(AVG(TIMESTAMPDIFF(SECOND, heure_debut_reelle, heure_fin_reelle)))
             FROM consultations
             WHERE sous_service_id = :id
               AND statut = 'traite'
               AND heure_debut_reelle IS NOT NULL
               AND heure_fin_reelle IS NOT NULL
               AND heure_fin_reelle > heure_debut_reelle
               AND DATE(heure_emission) >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)"
        );
        $stmt->execute([':id' => $ssId]);
        $moyReelle = (int)$stmt->fetchColumn();
        if ($moyReelle > 60) { // au moins 1 min, sinon données insuffisantes
            return $moyReelle;
        }
        // Priorité 2 : valeur configurée dans la table sous_services
        $stmt = $this->db->prepare(
            'SELECT COALESCE(duree_estimee, duree_rdv_defaut, 1800) FROM sous_services WHERE id = :id'
        );
        $stmt->execute([':id' => $ssId]);
        return (int)($stmt->fetchColumn() ?: 1800);
    }

    /**
     * Calcule l'heure de début estimée pour le prochain patient d'un jour donné,
     * POUR UN MÉDECIN DONNÉ. Un sous-service pouvant compter plusieurs médecins
     * travaillant en parallèle, chacun a sa propre file : la dernière
     * consultation prise en compte pour le calcul doit donc être celle de ce
     * médecin, pas celle de tout le sous-service (sinon le 2e médecin hériterait
     * de l'heure de fin du 1er au lieu de démarrer lui aussi à l'heure d'ouverture).
     * - 1er patient du médecin → debut = heure d'ouverture du service
     * - Patient suivant du même médecin → debut = heure_passage_estimee du dernier + duree_estimee du dernier
     *
     * @return array{heure_debut: string, heure_fin: string}  (format 'Y-m-d H:i:s')
     */
    public function calculerHeurePassageEstimee(int $ssId, string $date, int $medecinId = 0): array
    {
        $dureeEstimee = $this->getDureeEstimeeParSS($ssId);

        // Horaires d'ouverture (communs à tous les médecins du sous-service)
        $horaires = $this->getServiceHoraires($ssId);
        $heureOuverture = $horaires['horaires_ouverture'] ?? '08:00:00';

        // Dernière consultation planifiée ce jour POUR CE MÉDECIN UNIQUEMENT
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
            'SELECT c.*, p.nom AS patient_nom, p.prenom AS patient_prenom
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

        // 4. Utiliser l'heure choisie par le médecin (format "HH:MM")
        // On construit directement le datetime à partir de la date et l'heure sélectionnées.
        $heurePassage = $dateRdv . ' ' . $heureRdv . ':00';
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
        $heureRdvPourEdt = $heureRdv; // Heure choisie par le médecin (HH:MM)
        $this->reserverCreneauEdt(
            (int)$source['sous_service_id'],
            (int)$source['medecin_id'],
            $dateRdv,
            $heureRdvPourEdt
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

        return $this->calculerCreneauxDisponibles((int)$aff['ss_id'], $medecinId, $date);
    }

    /**
     * Calcule les créneaux disponibles pour un médecin, un sous-service et une
     * date donnés, en tenant compte de TOUT ce que l'administrateur a configuré
     * dans son dashboard :
     *  - horaires d'ouverture / fermeture du service,
     *  - pause du service (pause_debut / pause_fin) -> créneaux exclus,
     *  - jours de fermeture du service (jours_fermeture) -> journée exclue,
     *  - jours de travail du médecin (medecin_jours_travail) -> journée exclue
     *    si le médecin ne travaille pas ce jour-là,
     *  - congés du médecin (conges_medecins) -> journée exclue,
     *  - capacité horaire déjà réservée (consultations existantes).
     */
    public function calculerCreneauxDisponibles(int $ssId, int $medecinId, string $date): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.horaires_ouverture, s.horaires_fermeture, s.pause_debut, s.pause_fin,
                    s.jours_fermeture, ss.capacite_horaire
             FROM sous_services ss
             JOIN services s ON s.id = ss.service_id
             WHERE ss.id = :ss'
        );
        $stmt->execute([':ss' => $ssId]);
        $svc = $stmt->fetch();

        if (!$svc) return [];

        // Jour de la semaine demandé (1 = Lundi ... 7 = Dimanche)
        $jourSemaine = (int)date('N', strtotime($date));

        // 1) Le service est-il fermé ce jour-là (config admin) ?
        $joursFermeture = !empty($svc['jours_fermeture']) ? explode(',', $svc['jours_fermeture']) : [];
        if (in_array((string)$jourSemaine, $joursFermeture, true)) {
            return [];
        }

        // 2) Le médecin travaille-t-il ce jour-là (planning admin) ?
        $joursTravail = $this->getJoursTravailMedecin($medecinId);
        if (!empty($joursTravail) && !in_array($jourSemaine, array_map('intval', $joursTravail), true)) {
            return [];
        }

        // 3) Le médecin est-il en congé à cette date (planning admin) ?
        $stmtConge = $this->db->prepare(
            'SELECT id FROM conges_medecins
             WHERE medecin_id = :mid AND :date BETWEEN date_debut AND date_fin
             LIMIT 1'
        );
        $stmtConge->execute([':mid' => $medecinId, ':date' => $date]);
        if ($stmtConge->fetch()) {
            return [];
        }

        $ouv  = $svc['horaires_ouverture'] ?? '08:00:00';
        $ferm = $svc['horaires_fermeture'] ?? '18:00:00';
        $pauD = $svc['pause_debut'] ?? null;
        $pauF = $svc['pause_fin'] ?? null;
        $cap  = (int)($svc['capacite_horaire'] ?? 10);

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

        $creneaux    = [];
        $hOuv        = (int)date('H', strtotime($ouv));
        $hFerm       = (int)date('H', strtotime($ferm));
        $hPauseDebut = $pauD ? (int)date('H', strtotime($pauD)) : null;
        $hPauseFin   = $pauF ? (int)date('H', strtotime($pauF)) : null;

        for ($h = $hOuv; $h < $hFerm; $h++) {
            // 4) Exclure les heures qui tombent dans la pause configurée par l'admin
            if ($hPauseDebut !== null && $hPauseFin !== null && $h >= $hPauseDebut && $h < $hPauseFin) {
                continue;
            }

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
     * Réserve un rendez-vous en ligne pris directement par le patient depuis
     * l'application mobile, sur un créneau parmi ceux renvoyés par
     * getCreneauxDisponibles(). La disponibilité du créneau est revalidée
     * côté serveur (capacité, pause, jour de fermeture du service, jour de
     * travail et congés du médecin) pour rester cohérente avec le planning
     * configuré par l'administrateur, même si la demande ne provient pas
     * d'un appel préalable à getCreneauxDisponibles().
     */
    public function reserverRdvPatient(int $patientId, int $medecinId, string $dateRdv, string $heureRdv, string $motif = ''): array
    {
        if ($dateRdv <= date('Y-m-d')) {
            return ['success' => false, 'rdv_id' => null, 'message' => 'La date doit être dans le futur.'];
        }

        $aff = $this->getSousServiceMedecin($medecinId);
        if (!$aff) {
            return ['success' => false, 'rdv_id' => null, 'message' => 'Médecin introuvable ou non affecté à un service.'];
        }
        $ssId = (int)$aff['ss_id'];

        // Revalidation serveur du créneau (planning admin : horaires, pause,
        // jours de fermeture, jours de travail et congés du médecin).
        $disponibles = $this->calculerCreneauxDisponibles($ssId, $medecinId, $dateRdv);
        $heureNormalisee = date('H:00', strtotime($heureRdv));
        $creneauValide = false;
        foreach ($disponibles as $c) {
            if ($c['heure'] === $heureNormalisee) {
                $creneauValide = true;
                break;
            }
        }
        if (!$creneauValide) {
            return ['success' => false, 'rdv_id' => null, 'message' => 'Ce créneau n\'est plus disponible.'];
        }

        // Un seul RDV actif par patient, par service et par jour
        $stmt2 = $this->db->prepare(
            'SELECT id FROM consultations
             WHERE patient_id      = :pid
               AND sous_service_id = :ss
               AND DATE(heure_passage_estimee) = :date
               AND statut NOT IN ("annule", "absent")'
        );
        $stmt2->execute([':pid' => $patientId, ':ss' => $ssId, ':date' => $dateRdv]);
        if ($stmt2->fetch()) {
            return ['success' => false, 'rdv_id' => null, 'message' => 'Vous avez déjà un rendez-vous ce jour-là.'];
        }

        $stmtRang = $this->db->prepare(
            'SELECT COALESCE(MAX(rang), 0) + 1
             FROM consultations
             WHERE sous_service_id = :ss
               AND DATE(heure_passage_estimee) = :date
               AND statut NOT IN ("annule", "absent")'
        );
        $stmtRang->execute([':ss' => $ssId, ':date' => $dateRdv]);
        $rang = (int)$stmtRang->fetchColumn();

        $heurePassage = $dateRdv . ' ' . $heureNormalisee . ':00';
        $dureeEstimee = $this->getDureeEstimeeParSS($ssId);
        $motifPropre  = !empty($motif) ? htmlspecialchars(trim($motif)) : 'Rendez-vous en ligne';

        $stmtIns = $this->db->prepare(
            'INSERT INTO consultations
                (patient_id, sous_service_id, medecin_id, statut, rang, mode_prise,
                 heure_emission, heure_passage_estimee, duree_estimee, motif)
             VALUES
                (:patient_id, :ss, :medecin_id, "confirme", :rang, "LIGNE",
                 NOW(), :heure, :duree, :motif)'
        );
        $stmtIns->execute([
            ':patient_id' => $patientId,
            ':ss'         => $ssId,
            ':medecin_id' => $medecinId,
            ':rang'       => $rang,
            ':heure'      => $heurePassage,
            ':duree'      => $dureeEstimee,
            ':motif'      => $motifPropre,
        ]);
        $rdvId = (int)$this->db->lastInsertId();

        if (!$rdvId) {
            return ['success' => false, 'rdv_id' => null, 'message' => 'Erreur lors de la création du rendez-vous.'];
        }

        $this->reserverCreneauEdt($ssId, $medecinId, $dateRdv, $heureNormalisee);

        return [
            'success'   => true,
            'rdv_id'    => $rdvId,
            'message'   => 'Rendez-vous confirmé.',
            'date_rdv'  => $dateRdv,
            'heure_rdv' => $heureNormalisee,
        ];
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